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

            // Bug 1 : le snapshot est valable pour le CYCLE financier en cours
            // (salary_day → salary_day), pas pour le mois calendaire.
            [$start, $end] = $this->getFinancialCycleRange();

            return $snapshotDate->gte($start) && $snapshotDate->lt($end);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Bug 1 — Cycle financier
     * Début du cycle en cours : le salary_day du mois (ou du mois précédent
     * si on n'a pas encore atteint le salary_day ce mois-ci).
     * Non salarié / pas de salary_day : le cycle = le mois calendaire.
     *
     * @param Carbon|null $reference date de référence (par défaut : maintenant)
     */
    public function getFinancialCycleStart(?Carbon $reference = null): Carbon
    {
        $reference = $reference ? $reference->copy() : now();
        $profile   = $this->user->profile;

        if ($profile?->employment_type === 'salaried' && $profile?->salary_day) {
            $salaryDay = (int) $profile->salary_day;

            // Ancrage du salary_day sur le mois de référence (borné au nb de jours du mois)
            $anchorThisMonth = $reference->copy()
                ->setDay(min($salaryDay, $reference->daysInMonth))
                ->startOfDay();

            // Si on a déjà atteint le salary_day, le cycle a commencé ce mois-ci
            if ($reference->gte($anchorThisMonth)) {
                return $anchorThisMonth;
            }

            // Sinon, il a commencé au salary_day du mois précédent
            $prevMonth = $reference->copy()->subMonthNoOverflow();

            return $prevMonth
                ->setDay(min($salaryDay, $prevMonth->daysInMonth))
                ->startOfDay();
        }

        return $reference->copy()->startOfMonth();
    }

    /**
     * Bug 1 — Fin du cycle financier (borne EXCLUSIVE = début du cycle suivant).
     * Correspond au prochain salary_day.
     */
    public function getFinancialCycleEnd(?Carbon $reference = null): Carbon
    {
        $reference = $reference ? $reference->copy() : now();
        $profile   = $this->user->profile;

        if ($profile?->employment_type === 'salaried' && $profile?->salary_day) {
            $salaryDay = (int) $profile->salary_day;
            $start     = $this->getFinancialCycleStart($reference);
            $nextMonth = $start->copy()->addMonthNoOverflow();

            return $nextMonth
                ->setDay(min($salaryDay, $nextMonth->daysInMonth))
                ->startOfDay();
        }

        return $reference->copy()->endOfMonth();
    }

    /**
     * Bug 1 — Bornes [début, fin_exclusive] du cycle financier en cours.
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getFinancialCycleRange(): array
    {
        return [$this->getFinancialCycleStart(), $this->getFinancialCycleEnd()];
    }

    /**
     * Revenu de référence adapté à la situation réelle du mois
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
     * Utilisé uniquement pour l'affichage informatif — ne déduit plus du budget
     */
    public function getTotalMonthlyFixedCharges(): float
    {
        $profile = $this->user->profile;

        $fixedCharges = $this->user->fixedCharges()
            ->where('is_active', true)
            ->get()
            ->sum(fn ($c) => $c->monthly_equivalent);

        if ($profile?->spouse_contributes && $profile?->shared_fixed_charges) {
            $fixedCharges = $fixedCharges / 2;
        }

        return $fixedCharges;
    }

    /**
     * Nombre de jours restants dans le mois financier
     */
    public function getDaysLeftInFinancialMonth(): int
    {
        // Jours restants jusqu'au prochain salary_day (fin du cycle en cours).
        $end = $this->getFinancialCycleEnd();

        return max(0, (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay()));
    }

    /**
     * Reste à vivre = revenu de référence
     * Les charges fixes ne sont plus déduites automatiquement
     * Elles servent uniquement à l'affichage informatif
     *
     * Mode snapshot : montant déclaré à l'onboarding
     * Non salarié : proratisé selon les jours restants
     */
    public function getResteAVivre(): float
    {
        $profile = $this->user->profile;

        if ($this->isSnapshotMode()) {
            // Mode snapshot : on repart du solde déclaré
            return (float) $profile->current_month_remaining;
        }

        $baseIncome = $this->getSafeIncomeBaseline();

        // Non salarié : proratiser selon les jours restants
        if ($profile?->employment_type === 'non_salaried') {
            $daysLeft  = $this->getDaysLeftInFinancialMonth();
            $totalDays = now()->daysInMonth;

            if ($totalDays > 0) {
                return $baseIncome * ($daysLeft / $totalDays);
            }
        }

        return $baseIncome;
    }

    /**
     * Toutes les dépenses du mois (toutes transactions "out" sans distinction)
     */
    public function getCurrentMonthVariableSpending(): float
    {
        [$start, $end] = $this->getFinancialCycleRange();

        return (float) $this->user->transactions()
            ->where('direction', 'out')
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<', $end)
            ->sum('amount');
    }

    /**
     * Entrées de transactions ce mois
     */
    public function getCurrentMonthTransactionsIn(): float
    {
        [$start, $end] = $this->getFinancialCycleRange();

        return (float) $this->user->transactions()
            ->where('direction', 'in')
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<', $end)
            ->sum('amount');
    }

    /**
     * Budget réel restant ce mois
     * = reste à vivre − toutes les dépenses + toutes les entrées
     */
    public function getRealRemainingBudget(): float
    {
        return $this->getResteAVivre()
            - $this->getCurrentMonthVariableSpending()
            + $this->getCurrentMonthTransactionsIn();
    }

    /**
     * Dépenses par catégorie ce mois
     */
    public function getCurrentMonthSpendingByCategory(): array
    {
        [$start, $end] = $this->getFinancialCycleRange();

        return $this->user->transactions()
            ->where('direction', 'out')
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<', $end)
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(fn ($g) => $g->sum('amount'))
            ->toArray();
    }

    /**
     * Moyenne de dépenses par catégorie sur les 3 derniers mois
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
     * Catégories où les dépenses dépassent la moyenne
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
                    'category'     => $category,
                    'current'      => $amount,
                    'average'      => $avg,
                    'percent_over' => round((($amount / $avg) - 1) * 100),
                ];
            }
        }

        return $overspending;
    }

    /**
     * Consommation réelle de chaque charge fixe ce mois
     * (toutes les transactions liées à cette charge, quelle que soit leur nature)
     */
    public function getFixedChargesConsumption(): array
    {
        return $this->user->fixedCharges()
            ->where('is_active', true)
            ->get()
            ->map(function ($charge) {
                [$start, $end] = $this->getFinancialCycleRange();

                $spent = $this->user->transactions()
                    ->where('fixed_charge_id', $charge->id)
                    ->where('direction', 'out')
                    ->where('transacted_at', '>=', $start)
                    ->where('transacted_at', '<', $end)
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

        $daysUntil = (int) now()->startOfDay()->diffInDays(
            \Carbon\Carbon::parse($cycle->scheduled_date)->startOfDay()
        );

        return [
            'amount'     => $cycle->payout_amount ?? ($this->user->tontineGroups()->where('is_active', true)->first()?->contribution_amount * $this->user->tontineGroups()->where('is_active', true)->first()?->total_members),
            'date'       => $cycle->scheduled_date,
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

    /**
     * Bug 2 — Prochain jour de paie (= fin du cycle en cours / début du suivant).
     */
    public function getNextPayday(): Carbon
    {
        return $this->getFinancialCycleEnd();
    }

    /**
     * Bug 2 — Fenêtre de déclaration de salaire.
     * Ouverte à partir de (jour de paie du mois - 2) et tant que la paie du
     * cycle courant n'est PAS confirmée (le salaire peut arriver en retard).
     * Ancrée sur le salary_day du MOIS COURANT — surtout pas sur getNextPayday(),
     * qui bascule déjà sur le mois suivant le jour de la paie.
     */
    public function isSalaryDeclarationWindow(): bool
    {
        $profile = $this->user->profile;

        if ($profile?->employment_type !== 'salaried' || !$profile?->salary_day) {
            return false;
        }

        $salaryDay = (int) $profile->salary_day;
        $today     = now();

        // Jour de paie de CE mois (borné au nb de jours du mois).
        $thisMonthPayday = $today->copy()
            ->setDay(min($salaryDay, $today->daysInMonth))
            ->startOfDay();

        // Avant J-2 de la paie de ce mois : rien à afficher.
        if ($today->lt($thisMonthPayday->copy()->subDays(2))) {
            return false;
        }

        return !$this->hasSalaryBeenDeclaredForCurrentCycle();
    }

    /**
     * Bug 2 — La paie du cycle courant a-t-elle été confirmée ?
     * Vrai si salary_received_at (posé à l'onboarding « Oui » ou par le bouton
     * « J'ai reçu mon salaire ») tombe dans le cycle financier en cours.
     */
    public function hasSalaryBeenDeclaredForCurrentCycle(): bool
    {
        $profile = $this->user->profile;

        if (!$profile?->salary_received_at) {
            return false;
        }

        try {
            $receivedAt = $profile->salary_received_at instanceof Carbon
                ? $profile->salary_received_at
                : Carbon::parse($profile->salary_received_at);
        } catch (\Exception $e) {
            return false;
        }

        [$start, $end] = $this->getFinancialCycleRange();

        return $receivedAt->gte($start) && $receivedAt->lt($end);
    }

    // Compatibilité — plus utilisé mais gardé pour éviter les erreurs
    public function getFixedChargesSurplus(): float { return 0; }
    public function getMonthlyTontineCost(): float { return 0; }
}