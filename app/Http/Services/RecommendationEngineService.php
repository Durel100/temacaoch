<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\Recommendation;

class RecommendationEngineService
{
    protected FinancialCalculatorService $calculator;
    protected string $locale;

    public function __construct(private User $user)
    {
        $this->calculator = new FinancialCalculatorService($user);
        $this->locale     = $user->locale ?? 'fr';
    }

    private function msg(string $fr, string $en): string
    {
        return $this->locale === 'en' ? $en : $fr;
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
                    $over = number_format(abs($this->calculator->getRealRemainingBudget()), 0, ',', ' ');
                    return $this->msg(
                        "🔴 Budget dépassé de {$over} FCFA ce mois. Un découvert a été enregistré automatiquement.",
                        "🔴 Budget exceeded by {$over} FCFA this month. An overdraft has been recorded automatically."
                    );
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
                    $amount = number_format($debt->remaining_amount, 0, ',', ' ');
                    return $this->msg(
                        "💸 Dette à {$debt->interest_rate}% ({$debt->label}) : {$amount} FCFA restants. Rembourse en priorité.",
                        "💸 Debt at {$debt->interest_rate}% ({$debt->label}): {$amount} FCFA remaining. Repay first."
                    );
                },
            ],

            [
                'id'        => 'late_tontine_contribution',
                'priority'  => 1,
                'condition' => fn () => count($this->calculator->getLateTontineContributions()) > 0,
                'message'   => function () {
                    $count = count($this->calculator->getLateTontineContributions());
                    return $this->msg(
                        "⏰ {$count} cotisation(s) tontine en retard. Régularise rapidement pour ne pas perdre ta place.",
                        "⏰ {$count} tontine contribution(s) overdue. Pay quickly to keep your spot."
                    );
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
                    $remaining = number_format($this->calculator->getRealRemainingBudget(), 0, ',', ' ');
                    $days      = $this->calculator->getDaysLeftInFinancialMonth();
                    $perDay    = $days > 0 ? number_format(round($this->calculator->getRealRemainingBudget() / $days), 0, ',', ' ') : 0;
                    return $this->msg(
                        "⚠️ Il ne te reste que {$remaining} FCFA pour {$days} jours soit environ {$perDay} FCFA/jour.",
                        "⚠️ You only have {$remaining} FCFA left for {$days} days, about {$perDay} FCFA/day."
                    );
                },
            ],

            [
                'id'        => 'overspending_category',
                'priority'  => 2,
                'condition' => fn () => count($this->calculator->getOverspendingCategories()) > 0,
                'message'   => function () {
                    $over    = $this->calculator->getOverspendingCategories()[0];
                    $current = number_format($over['current'], 0, ',', ' ');
                    return $this->msg(
                        "📈 {$over['category']} : {$current} FCFA dépensés, {$over['percent_over']}% au-dessus de ta moyenne habituelle.",
                        "📈 {$over['category']}: {$current} FCFA spent, {$over['percent_over']}% above your usual average."
                    );
                },
            ],

            // ══════════════════════════════════════════════════════════
            // PRIORITÉ 3 — APRÈS GROSSE ENTRÉE D'ARGENT
            // ══════════════════════════════════════════════════════════

            [
                'id'        => 'big_income_debt_reminder',
                'priority'  => 3,
                'condition' => function () {
                    $monthIn  = $this->calculator->getCurrentMonthTransactionsIn();
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    $hasDebts = $this->user->debts()->where('remaining_amount', '>', 0)->exists();
                    return $monthIn > ($baseline * 0.5) && $hasDebts;
                },
                'message'   => function () {
                    $totalDebt = number_format($this->user->debts()->where('remaining_amount', '>', 0)->sum('remaining_amount'), 0, ',', ' ');
                    $monthIn   = number_format($this->calculator->getCurrentMonthTransactionsIn(), 0, ',', ' ');
                    return $this->msg(
                        "💰 Tu as reçu {$monthIn} FCFA ce mois. Tu as encore {$totalDebt} FCFA de dettes. C'est le bon moment pour rembourser.",
                        "💰 You received {$monthIn} FCFA this month. You still have {$totalDebt} FCFA in debt. A good time to repay."
                    );
                },
            ],

            [
                'id'        => 'big_income_goals_reminder',
                'priority'  => 3,
                'condition' => function () {
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
                    $missing = number_format($goal->target_amount - $goal->current_amount, 0, ',', ' ');
                    return $this->msg(
                        "🎯 Tu as reçu de l'argent ce mois ! Objectif \"{$goal->label}\" : il manque encore {$missing} FCFA. C'est le moment d'épargner.",
                        "🎯 You received money this month! Goal \"{$goal->label}\": {$missing} FCFA left to go. Time to save."
                    );
                },
            ],

            [
                'id'        => 'big_income_tontine_reminder',
                'priority'  => 3,
                'condition' => function () {
                    $monthIn  = $this->calculator->getCurrentMonthTransactionsIn();
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    if ($monthIn <= ($baseline * 0.5)) return false;
                    return $this->user->tontineGroups()
                        ->where('is_active', true)
                        ->get()
                        ->filter(function ($group) {
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
                    return $this->msg(
                        "🤝 Tu as reçu de l'argent ce mois. N'oublie pas tes cotisations tontine du mois !",
                        "🤝 You received money this month. Don't forget your tontine contributions!"
                    );
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
                        return $this->msg(
                            "🎉 C'est aujourd'hui que tu reçois ta tontine : {$amount} FCFA ! Planifie bien son utilisation.",
                            "🎉 Today is your tontine payout day: {$amount} FCFA! Plan how to use it wisely."
                        );
                    }
                    return $this->msg(
                        "🔔 Réception tontine dans {$days} jour(s) : {$amount} FCFA. Réfléchis à comment l'utiliser.",
                        "🔔 Tontine payout in {$days} day(s): {$amount} FCFA. Think about how to use it."
                    );
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
                    $amount    = number_format($payout['amount'], 0, ',', ' ');
                    $totalDebt = number_format($this->user->debts()->where('remaining_amount', '>', 0)->sum('remaining_amount'), 0, ',', ' ');
                    return $this->msg(
                        "💡 Ta tontine ({$amount} FCFA) arrive bientôt. Tu as {$totalDebt} FCFA de dettes. Pense à en rembourser une partie.",
                        "💡 Your tontine ({$amount} FCFA) is coming soon. You have {$totalDebt} FCFA in debt. Consider repaying some."
                    );
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
                    if (!$goal) return true;
                    return $goal->current_amount < $this->calculator->getSafeIncomeBaseline();
                },
                'message'   => function () {
                    $baseline = $this->calculator->getSafeIncomeBaseline();
                    $goal     = $this->user->financialGoals()
                        ->where('label', 'like', '%urgence%')
                        ->orWhere('label', 'like', '%emergency%')
                        ->first();
                    $missing  = number_format($baseline - ($goal?->current_amount ?? 0), 0, ',', ' ');
                    return $this->msg(
                        "🛡️ Fonds d'urgence insuffisant. Il te manque {$missing} FCFA pour avoir 1 mois de revenu en réserve.",
                        "🛡️ Emergency fund too low. You need {$missing} FCFA more to have 1 month of income in reserve."
                    );
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
                    $goal    = $this->user->financialGoals()->get()
                        ->filter(fn ($g) => $g->progress_percent >= 80 && $g->progress_percent < 100)
                        ->first();
                    $missing = number_format($goal->target_amount - $goal->current_amount, 0, ',', ' ');
                    return $this->msg(
                        "🎯 Objectif \"{$goal->label}\" à {$goal->progress_percent}% ! Plus que {$missing} FCFA. Tu y es presque !",
                        "🎯 Goal \"{$goal->label}\" at {$goal->progress_percent}%! Only {$missing} FCFA to go. Almost there!"
                    );
                },
            ],

            [
                'id'        => 'goal_target_reached',
                'priority'  => 5,
                'condition' => function () {
                    return $this->user->financialGoals()->get()
                        ->filter(fn ($g) => $g->progress_percent >= 100)
                        ->isNotEmpty();
                },
                'message'   => function () {
                    $goal = $this->user->financialGoals()->get()
                        ->filter(fn ($g) => $g->progress_percent >= 100)
                        ->first();
                    return $this->msg(
                        "🎉 Objectif \"{$goal->label}\" atteint ! Félicitations. Tu peux maintenant le clôturer ou en créer un nouveau.",
                        "🎉 Goal \"{$goal->label}\" reached! Congratulations. You can close it or create a new one."
                    );
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
                        return $this->msg(
                            "📅 Fin de mois dans {$daysLeft} jour(s). Il te reste {$amount} FCFA. Bien joué !",
                            "📅 End of month in {$daysLeft} day(s). You have {$amount} FCFA left. Well done!"
                        );
                    }
                    return $this->msg(
                        "📅 Fin de mois dans {$daysLeft} jour(s). Budget dépassé de {$amount} FCFA. Fais attention.",
                        "📅 End of month in {$daysLeft} day(s). Budget exceeded by {$amount} FCFA. Be careful."
                    );
                },
            ],

            [
                'id'        => 'debt_all_paid',
                'priority'  => 6,
                'condition' => function () {
                    $hadDebts    = $this->user->debts()->exists();
                    $activeDebts = $this->user->debts()->where('remaining_amount', '>', 0)->exists();
                    return $hadDebts && !$activeDebts;
                },
                'message'   => function () {
                    return $this->msg(
                        "🎊 Toutes tes dettes sont remboursées ! C'est une excellente nouvelle. Pense maintenant à épargner.",
                        "🎊 All your debts are paid off! Excellent news. Now think about saving."
                    );
                },
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
                    $fmt     = number_format($total, 0, ',', ' ');
                    return $this->msg(
                        "👨‍👩‍👧 Argent de poche famille : {$fmt} FCFA/mois ({$percent}% de ton revenu).",
                        "👨‍👩‍👧 Family allowances: {$fmt} FCFA/month ({$percent}% of your income)."
                    );
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