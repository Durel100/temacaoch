<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\TontineContribution;
use Carbon\Carbon;

class FinancialCalculatorService
{
    public function __construct(private User $user) {}

    /**
     * Vérifie si on est en mode snapshot actif ce mois
     * = l'utilisateur a déclaré son solde actuel à l'onboarding ce mois-ci
     */
    public function isSnapshotMode(): bool
    {
        $profile = $this->user->profile;
        if (!$profile || $profile->current_month_remaining === null) return false;
        if (!$profile->remaining_snapshot_date) return false;

        try {
            $snapshotDate = $profile->remaining_snapshot_date instanceof Carbon
                ? $profile->remaining_snapshot_date
                : Carbon::parse($profile->remaining_snapshot_date);

            return $snapshotDate->month === now()->month
                && $snapshotDate->year === now()->year;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revenu de référence adapté à la situation réelle du mois
     *
     * Mode snapshot : montant déclaré à l'onboarding (solde actuel)
     * Mode standard : minimum des 3 derniers mois ou revenu onboarding
     * + contribution du conjoint si applicable
     */
    public function getSafeIncomeBaseline(): float
    {
        $profile = $this->user->profile;

        if ($this->isSnapshotMode()) {
            return (float) $profile->current_month_remaining;
        }

        $last3Months = $this->user->incomeRecords()
            ->where('received_at', '>=', now()->subMonths(3))
            ->get()
            ->groupBy(fn ($r) => Carbon::parse($r->received_at)->format('Y-m'))
            ->map(fn ($g) => $g->sum('amount'));

        $baseIncome = $last3Months->isNotEmpty()
            ? (float) $last3Months->min()
            : (float) $this->user->incomeSources()
                ->where('is_active', true)
                ->sum('amount');

        // Ajouter la contribution du conjoint si applicable
        if (
            $profile?->spouse_contributes &&
            $profile?->spouse_monthly_contribution > 0
        ) {
            $baseIncome += (float) $profile->spouse_monthly_contribution;
        }

        return $baseIncome;
    }

    /**
     * Total des charges fixes mensuelles
     * Inclut : charges fixes, tontines, argent de poche, remboursements dettes
     * Si charges partagées avec conjoint : charges fixes divisées par 2
     */
    public function getTotalMonthlyFixedCharges(): float
    {
        $profile = $this->user->profile;

        $fixedCharges = $this->user->fixedCharges()
            ->where('is_active', true)
            ->get()
            ->sum(fn ($c) => $c->monthly_equivalent);

        // Partager les charges si conjoint contribue et charges partagées
        if ($profile?->spouse_contributes && $profile?->shared_fixed_charges) {
            $fixedCharges = $fixedCharges / 2;
        }

        $tontineContributions = $this->getMonthlyTontineCost();

        $allowances = $this->user->dependents()
            ->get()
            ->sum(fn ($d) => $d->monthly_allowance_cost);

        $debtPayments = $this->user->debts()
            ->whereNotNull('monthly_payment')
            ->sum('monthly_payment');

        return $fixedCharges + $tontineContributions + $allowances + $debtPayments;
    }

    /**
     * Coût mensuel des cotisations tontine actives
     * Utilise cycle_days pour supporter tous les cycles personnalisés
     */
    public function getMonthlyTontineCost(): float
    {
        return $this->user->tontineGroups()
            ->where('is_active', true)
            ->get()
            ->sum(function ($group) {
                $cycleDays      = $group->cycle_days ?? 30;
                $cyclesPerMonth = 30 / $cycleDays;
                return $group->contribution_amount * $cyclesPerMonth;
            });
    }

    /**
     * Nombre de jours restants dans le "mois financier" de l'utilisateur
     *
     * Salarié  : jours jusqu'au prochain jour de paye (mois glissant)
     * Non salarié : jours restants dans le mois calendaire
     */
    public function getDaysLeftInFinancialMonth(): int
    {
        $profile = $this->user->profile;

        if ($profile?->employment_type === 'salaried' && $profile?->salary_day) {
            $salaryDay = (int) $profile->salary_day;
            $today     = now()->day;

            if ($today < $salaryDay) {
                return $salaryDay - $today;
            } else {
                $nextPayday = now()->copy()->addMonth()->setDay($salaryDay);
                return (int) now()->diffInDays($nextPayday);
            }
        }

        return now()->daysInMonth - now()->day;
    }

    /**
     * Reste à vivre adapté selon le type d'emploi et le mode snapshot
     *
     * Mode snapshot : montant restant − charges encore à payer
     * Salarié standard : revenu − charges (mois glissant complet)
     * Non salarié : (revenu − charges) × prorata jours restants
     */
    public function getResteAVivre(): float
    {
        $profile = $this->user->profile;

        if ($this->isSnapshotMode()) {
            return (float) (
                $profile->current_month_remaining
                - ($profile->remaining_fixed_charges_this_month ?? 0)
            );
        }

        $baseRemaining = $this->getSafeIncomeBaseline()
            - $this->getTotalMonthlyFixedCharges();

        // Non salarié : proratiser selon les jours restants
        if ($profile?->employment_type === 'non_salaried') {
            $daysLeft  = $this->getDaysLeftInFinancialMonth();
            $totalDays = now()->daysInMonth;

            if ($totalDays > 0) {
                return $baseRemaining * ($daysLeft / $totalDays);
            }
        }

        return $baseRemaining;
    }

    /**
     * Dépenses libres (hors charges fixes) ce mois
     */
    public function getCurrentMonthVariableSpending(): float
    {
        return (float) $this->user->transactions()
            ->where('direction', 'out')
            ->whereNull('fixed_charge_id')
            ->whereMonth('transacted_at', now()->month)
            ->whereYear('transacted_at', now()->year)
            ->sum('amount');
    }

    /**
     * Entrées de transactions ce mois (hors revenu principal)
     */
    public function getCurrentMonthTransactionsIn(): float
    {
        return (float) $this->user->transactions()
            ->where('direction', 'in')
            ->whereMonth('transacted_at', now()->month)
            ->whereYear('transacted_at', now()->year)
            ->sum('amount');
    }

    /**
     * Surplus des budgets fixes dépassés ce mois
     * Ce surplus est prélevé sur le reste à vivre
     */
    public function getFixedChargesSurplus(): float
    {
        return $this->user->fixedCharges()
            ->where('is_active', true)
            ->get()
            ->sum(function ($charge) {
                $spent = $this->user->transactions()
                    ->where('fixed_charge_id', $charge->id)
                    ->where('direction', 'out')
                    ->whereMonth('transacted_at', now()->month)
                    ->whereYear('transacted_at', now()->year)
                    ->sum('amount');
                return max(0, $spent - $charge->monthly_equivalent);
            });
    }

    /**
     * Budget réel restant ce mois
     * = reste à vivre − dépenses libres − surplus charges fixes + entrées
     */
    public function getRealRemainingBudget(): float
    {
        return $this->getResteAVivre()
            - $this->getCurrentMonthVariableSpending()
            - $this->getFixedChargesSurplus()
            + $this->getCurrentMonthTransactionsIn();
    }

    /**
     * Dépenses par catégorie ce mois
     */
    public function getCurrentMonthSpendingByCategory(): array
    {
        return $this->user->transactions()
            ->where('direction', 'out')
            ->whereMonth('transacted_at', now()->month)
            ->whereYear('transacted_at', now()->year)
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(fn ($g) => $g->sum('amount'))
            ->toArray();
    }

    /**
     * Moyenne de dépenses par catégorie sur les 3 derniers mois
     * Sert de référence pour détecter les dépassements
     */
    public function getAverageSpendingByCategory(): array
    {
        $transactions = $this->user->transactions()
            ->where('direction', 'out')
            ->where('transacted_at', '>=', now()->subMonths(3)->startOfMonth())
            ->where('transacted_at', '<', now()->startOfMonth())
            ->with('category')
            ->get()
            ->groupBy('category.name');

        return $transactions->map(function ($group) {
            $monthlyTotals = $group
                ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->format('Y-m'))
                ->map(fn ($g) => $g->sum('amount'));
            return $monthlyTotals->avg();
        })->toArray();
    }

    /**
     * Catégories où les dépenses dépassent la moyenne d'un seuil donné
     * Le seuil est calibré selon budget_preference de l'utilisateur
     */
    public function getOverspendingCategories(float $thresholdMultiplier = 1.3): array
    {
        $current      = $this->getCurrentMonthSpendingByCategory();
        $average      = $this->getAverageSpendingByCategory();
        $overspending = [];

        foreach ($current as $category => $amount) {
            $avg = $average[$category] ?? null;
            if ($avg && $amount > $avg * $thresholdMultiplier) {
                $overspending[] = [
                    'category'    => $category,
                    'current'     => $amount,
                    'average'     => $avg,
                    'percent_over' => round((($amount / $avg) - 1) * 100),
                ];
            }
        }

        return $overspending;
    }

    /**
     * Consommation réelle de chaque charge fixe ce mois
     */
    public function getFixedChargesConsumption(): array
    {
        return $this->user->fixedCharges()
            ->where('is_active', true)
            ->get()
            ->map(function ($charge) {
                $spent = $this->user->transactions()
                    ->where('fixed_charge_id', $charge->id)
                    ->where('direction', 'out')
                    ->whereMonth('transacted_at', now()->month)
                    ->whereYear('transacted_at', now()->year)
                    ->sum('amount');

                $budget = $charge->monthly_equivalent;

                return [
                    'id'        => $charge->id,
                    'label'     => $charge->label,
                    'budget'    => $budget,
                    'spent'     => (float) $spent,
                    'remaining' => max(0, $budget - $spent),
                    'percent'   => $budget > 0
                        ? min(100, round(($spent / $budget) * 100))
                        : 0,
                    'is_over'   => $spent > $budget,
                    'surplus'   => max(0, $spent - $budget),
                ];
            })->toArray();
    }

    /**
     * Prochaine réception de tontine dans les N jours
     */
    public function getUpcomingTontinePayout(int $withinDays = 14): ?array
    {
        $cycle = $this->user->tontineGroups()
            ->where('is_active', true)
            ->get()
            ->map(fn ($g) => $g->myPayoutCycle())
            ->filter()
            ->filter(fn ($c) =>
                now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($c->scheduled_date)->startOfDay(), false) <= $withinDays &&
                now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($c->scheduled_date)->startOfDay(), false) >= 0
            )
            ->sortBy('scheduled_date')
            ->first();

        if (!$cycle) return null;

        // Calcul correct : comparer uniquement les dates sans l'heure
        $daysUntil = (int) now()->startOfDay()->diffInDays(
            \Carbon\Carbon::parse($cycle->scheduled_date)->startOfDay()
        );

        return [
            'amount'    => $cycle->payout_amount ?? ($this->user->tontineGroups()->where('is_active', true)->first()?->contribution_amount * $this->user->tontineGroups()->where('is_active', true)->first()?->total_members),
            'date'      => $cycle->scheduled_date,
            'days_until' => $daysUntil,
        ];
    }

    /**
     * Cotisations tontine en retard
     */
    public function getLateTontineContributions(): array
    {
        return TontineContribution::whereHas('cycle.group', function ($q) {
                $q->where('user_id', $this->user->id);
            })
            ->where('status', 'pending')
            ->whereHas('cycle', fn ($q) => $q->where('scheduled_date', '<', now()))
            ->with('cycle.group')
            ->get()
            ->toArray();
    }
}