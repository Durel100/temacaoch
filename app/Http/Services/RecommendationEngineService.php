<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\Recommendation;

class RecommendationEngineService
{
    protected FinancialCalculatorService $calculator;

    public function __construct(private User $user)
    {
        $this->calculator = new FinancialCalculatorService($user);
    }

    protected function rules(): array
    {
        return [

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 1 — URGENCES IMMÉDIATES
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'budget_exceeded',
                'priority'  => 1,
                'condition' => fn () => $this->calculator->getRealRemainingBudget() < 0,
                'message'   => function () {
                    $over = abs($this->calculator->getRealRemainingBudget());
                    return "🔴 Budget dépassé de " . number_format($over, 0, ',', ' ')
                        . " FCFA ce mois. Un découvert a été enregistré automatiquement.";
                },
            ],

            [
                'id'        => 'high_interest_debt',
                'priority'  => 1,
                'condition' => fn () => $this->user->debts()
                    ->where('interest_rate', '>', 15)
                    ->where('remaining_amount', '>', 0)
                    ->exists(),
                'message'   => function () {
                    $debt = $this->user->debts()
                        ->where('interest_rate', '>', 15)
                        ->where('remaining_amount', '>', 0)
                        ->orderByDesc('interest_rate')
                        ->first();
                    return "💸 Dette à {$debt->interest_rate}% ({$debt->label}) : "
                        . number_format($debt->remaining_amount, 0, ',', ' ')
                        . " FCFA restants. Rembourse en priorité.";
                },
            ],

            [
                'id'        => 'late_tontine_contribution',
                'priority'  => 1,
                'condition' => fn () => count($this->calculator->getLateTontineContributions()) > 0,
                'message'   => function () {
                    $count = count($this->calculator->getLateTontineContributions());
                    return "⏰ {$count} cotisation(s) tontine en retard. Régularise rapidement pour ne pas perdre ta place.";
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 2 — ALERTES BUDGET
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'budget_almost_exhausted',
                'priority'  => 2,
                'condition' => function () {
                    $remaining = $this->calculator->getRealRemainingBudget();
                    $base      = $this->calculator->getResteAVivre();
                    if ($base <= 0 || $remaining < 0) return false;
                    return ($remaining / $base) * 100 < 20;
                },
                'message'   => function () {
                    $remaining = $this->calculator->getRealRemainingBudget();
                    $days      = $this->calculator->getDaysLeftInFinancialMonth();
                    $perDay    = $days > 0 ? round($remaining / $days) : 0;
                    return "⚠️ Il ne te reste que "
                        . number_format($remaining, 0, ',', ' ')
                        . " FCFA pour {$days} jours soit environ "
                        . number_format($perDay, 0, ',', ' ')
                        . " FCFA/jour.";
                },
            ],

            [
                'id'        => 'overspending_category',
                'priority'  => 2,
                'condition' => fn () => count($this->calculator->getOverspendingCategories()) > 0,
                'message'   => function () {
                    $over = $this->calculator->getOverspendingCategories()[0];
                    return "📈 {$over['category']} : "
                        . number_format($over['current'], 0, ',', ' ')
                        . " FCFA dépensés, {$over['percent_over']}% au-dessus de ta moyenne habituelle.";
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 3 — APRÈS GROSSE ENTRÉE D'ARGENT
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'big_income_debt_reminder',
                'priority'  => 3,
                'condition' => function () {
                    // Grosse entrée ce mois (> 50% du revenu de référence)
                    $monthIn   = $this->calculator->getCurrentMonthTransactionsIn();
                    $baseline  = $this->calculator->getSafeIncomeBaseline();
                    $hasDebts  = $this->user->debts()
                        ->where('remaining_amount', '>', 0)
                        ->exists();
                    return $monthIn > ($baseline * 0.5) && $hasDebts;
                },
                'message'   => function () {
                    $totalDebt = $this->user->debts()
                        ->where('remaining_amount', '>', 0)
                        ->sum('remaining_amount');
                    $monthIn   = $this->calculator->getCurrentMonthTransactionsIn();
                    return "💰 Tu as reçu "
                        . number_format($monthIn, 0, ',', ' ')
                        . " FCFA ce mois. Tu as encore "
                        . number_format($totalDebt, 0, ',', ' ')
                        . " FCFA de dettes. C'est le bon moment pour rembourser.";
                },
            ],

            [
                'id'        => 'big_income_goals_reminder',
                'priority'  => 3,
                'condition' => function () {
                    // Grosse entrée ce mois et des objectifs non atteints
                    $monthIn  = $this->calculator->getCurrentMonthTransactionsIn();
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    $hasGoals = $this->user->financialGoals()
                        ->where('current_amount', '<', \Illuminate\Support\Facades\DB::raw('target_amount'))
                        ->exists();
                    return $monthIn > ($baseline * 0.5) && $hasGoals;
                },
                'message'   => function () {
                    $goal = $this->user->financialGoals()
                        ->where('current_amount', '<', \Illuminate\Support\Facades\DB::raw('target_amount'))
                        ->orderByRaw('(target_amount - current_amount) ASC')
                        ->first();
                    if (!$goal) return '';
                    $remaining = $goal->target_amount - $goal->current_amount;
                    return "🎯 Tu as reçu de l'argent ce mois ! Objectif \"{$goal->label}\" : il manque encore "
                        . number_format($remaining, 0, ',', ' ')
                        . " FCFA. C'est le moment d'épargner.";
                },
            ],

            [
                'id'        => 'big_income_tontine_reminder',
                'priority'  => 3,
                'condition' => function () {
                    // Grosse entrée ET cotisation tontine du mois pas encore payée
                    $monthIn  = $this->calculator->getCurrentMonthTransactionsIn();
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    if ($monthIn <= ($baseline * 0.5)) return false;

                    return $this->user->tontineGroups()
                        ->where('is_active', true)
                        ->get()
                        ->filter(function ($group) {
                            // A-t-il une cotisation due ce mois non payée ?
                            return $group->cycles()
                                ->whereMonth('scheduled_date', now()->month)
                                ->whereYear('scheduled_date', now()->year)
                                ->where('is_my_turn', false)
                                ->whereDoesntHave('contribution', fn ($q) => $q->where('status', 'paid'))
                                ->exists();
                        })
                        ->isNotEmpty();
                },
                'message'   => function () {
                    return "🤝 Tu as reçu de l'argent ce mois. N'oublie pas tes cotisations tontine du mois !";
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 4 — TONTINES & PLANIFICATION
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'tontine_payout_incoming',
                'priority'  => 4,
                'condition' => fn () => $this->calculator->getUpcomingTontinePayout() !== null,
                'message'   => function () {
                    $payout = $this->calculator->getUpcomingTontinePayout();
                    $days   = $payout['days_until'];
                    $amount = number_format($payout['amount'], 0, ',', ' ');
                    if ($days === 0) {
                        return "🎉 C'est aujourd'hui que tu reçois ta tontine : {$amount} FCFA ! Planifie bien son utilisation.";
                    }
                    return "🔔 Réception tontine dans {$days} jour(s) : {$amount} FCFA. Réfléchis à comment l'utiliser.";
                },
            ],

            [
                'id'        => 'tontine_payout_plan_debt',
                'priority'  => 4,
                'condition' => function () {
                    $payout = $this->calculator->getUpcomingTontinePayout();
                    if (!$payout || $payout['days_until'] > 7) return false;
                    return $this->user->debts()->where('remaining_amount', '>', 0)->exists();
                },
                'message'   => function () {
                    $payout    = $this->calculator->getUpcomingTontinePayout();
                    $totalDebt = $this->user->debts()->where('remaining_amount', '>', 0)->sum('remaining_amount');
                    $amount    = number_format($payout['amount'], 0, ',', ' ');
                    $debt      = number_format($totalDebt, 0, ',', ' ');
                    return "💡 Ta tontine ({$amount} FCFA) arrive bientôt. Tu as {$debt} FCFA de dettes. Pense à en rembourser une partie.";
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 5 — OBJECTIFS & ÉPARGNE
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'no_emergency_fund',
                'priority'  => 5,
                'condition' => function () {
                    $goal = $this->user->financialGoals()
                        ->where('label', 'like', '%urgence%')
                        ->orWhere('label', 'like', '%emergency%')
                        ->first();
                    if (!$goal) {
                        // Pas d'objectif urgence du tout
                        return true;
                    }
                    return $goal->current_amount < $this->calculator->getSafeIncomeBaseline();
                },
                'message'   => function () {
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    $goal     = $this->user->financialGoals()
                        ->where('label', 'like', '%urgence%')
                        ->orWhere('label', 'like', '%emergency%')
                        ->first();
                    $current  = $goal?->current_amount ?? 0;
                    $missing  = number_format($baseline - $current, 0, ',', ' ');
                    return "🛡️ Fonds d'urgence insuffisant. Il te manque {$missing} FCFA pour avoir 1 mois de revenu en réserve.";
                },
            ],

            [
                'id'        => 'goal_close_to_target',
                'priority'  => 5,
                'condition' => function () {
                    return $this->user->financialGoals()
                        ->get()
                        ->filter(fn ($g) => $g->progress_percent >= 80 && $g->progress_percent < 100)
                        ->isNotEmpty();
                },
                'message'   => function () {
                    $goal = $this->user->financialGoals()
                        ->get()
                        ->filter(fn ($g) => $g->progress_percent >= 80 && $g->progress_percent < 100)
                        ->first();
                    $missing = number_format($goal->target_amount - $goal->current_amount, 0, ',', ' ');
                    return "🎯 Objectif \"{$goal->label}\" à {$goal->progress_percent}% ! Plus que {$missing} FCFA. Tu y es presque !";
                },
            ],

            [
                'id'        => 'goal_target_reached',
                'priority'  => 5,
                'condition' => function () {
                    return $this->user->financialGoals()
                        ->get()
                        ->filter(fn ($g) => $g->progress_percent >= 100)
                        ->isNotEmpty();
                },
                'message'   => function () {
                    $goal = $this->user->financialGoals()
                        ->get()
                        ->filter(fn ($g) => $g->progress_percent >= 100)
                        ->first();
                    return "🎉 Objectif \"{$goal->label}\" atteint ! Félicitations. Tu peux maintenant le clôturer ou en créer un nouveau.";
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 6 — INFORMATIONS & RAPPELS
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'end_of_month_reminder',
                'priority'  => 6,
                'condition' => function () {
                    $daysLeft = $this->calculator->getDaysLeftInFinancialMonth();
                    return $daysLeft <= 5 && $daysLeft > 0;
                },
                'message'   => function () {
                    $daysLeft  = $this->calculator->getDaysLeftInFinancialMonth();
                    $remaining = $this->calculator->getRealRemainingBudget();
                    $amount    = number_format(abs($remaining), 0, ',', ' ');
                    if ($remaining >= 0) {
                        return "📅 Fin de mois dans {$daysLeft} jour(s). Il te reste {$amount} FCFA. Bien joué !";
                    }
                    return "📅 Fin de mois dans {$daysLeft} jour(s). Budget dépassé de {$amount} FCFA. Fais attention.";
                },
            ],

            [
                'id'        => 'debt_all_paid',
                'priority'  => 6,
                'condition' => function () {
                    $hadDebts   = $this->user->debts()->exists();
                    $activeDebts = $this->user->debts()->where('remaining_amount', '>', 0)->exists();
                    return $hadDebts && !$activeDebts;
                },
                'message'   => fn () => "🎊 Toutes tes dettes sont remboursées ! C'est une excellente nouvelle. Pense maintenant à épargner.",
            ],

            [
                'id'        => 'allowance_cost_visibility',
                'priority'  => 6,
                'condition' => function () {
                    return $this->user->dependents()->get()->sum(fn ($d) => $d->monthly_allowance_cost) > 0;
                },
                'message'   => function () {
                    $total   = $this->user->dependents()->get()->sum(fn ($d) => $d->monthly_allowance_cost);
                    $income  = $this->calculator->getSafeIncomeBaseline();
                    $percent = $income > 0 ? round(($total / $income) * 100) : 0;
                    return "👨‍👩‍👧 Argent de poche famille : "
                        . number_format($total, 0, ',', ' ')
                        . " FCFA/mois ({$percent}% de ton revenu).";
                },
            ],

        ];
    }

    public function evaluate(): array
    {
        $triggered = [];

        foreach ($this->rules() as $rule) {
            try {
                if (($rule['condition'])()) {
                    $message = ($rule['message'])();
                    if ($message) {
                        $triggered[] = [
                            'rule_id'  => $rule['id'],
                            'priority' => $rule['priority'],
                            'message'  => $message,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Silencieux — une règle cassée ne doit pas bloquer les autres
                \Log::warning("RecommendationEngine rule {$rule['id']} failed: " . $e->getMessage());
            }
        }

        usort($triggered, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $triggered;
    }

    public function evaluateAndStore(): void
    {
        $triggered = $this->evaluate();

        foreach ($triggered as $item) {
            $exists = $this->user->recommendations()
                ->where('rule_id', $item['rule_id'])
                ->where('is_read', false)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if (!$exists) {
                Recommendation::create([
                    'user_id'      => $this->user->id,
                    'rule_id'      => $item['rule_id'],
                    'priority'     => $item['priority'],
                    'message'      => $item['message'],
                    'triggered_at' => now(),
                    'expires_at'   => now()->addDays(7),
                ]);
            }
        }
    }

    public function getTopRecommendations(int $limit = 3): array
    {
        return array_slice($this->evaluate(), 0, $limit);
    }
}