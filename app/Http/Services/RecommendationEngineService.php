<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\Recommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommendationEngineService
{
    protected FinancialCalculatorService $calculator;

    public function __construct(private User $user)
    {
        $this->calculator = new FinancialCalculatorService($user);
    }

    protected function getOverspendingThreshold(): float
    {
        return match ($this->user->profile?->spending_tendency) {
            'spends_quickly' => 0.70,
            'saves'          => 0.90,
            default          => 0.80,
        };
    }

    protected function getCategoryOverspendingMultiplier(): float
    {
        return match ($this->user->profile?->budget_preference) {
            'strict'   => 1.20,
            'flexible' => 1.40,
            default    => 1.30,
        };
    }

    protected function getEmergencyFundPriority(): int
    {
        return match ($this->user->profile?->budget_struggle_frequency) {
            'often'     => 1,
            'sometimes' => 2,
            default     => 3,
        };
    }

    protected function rules(): array
    {
        $resteAVivre      = $this->calculator->getResteAVivre();
        $variableSpending = $this->calculator->getCurrentMonthVariableSpending();
        $spendingPercent  = $resteAVivre > 0 ? $variableSpending / $resteAVivre : 0;

        return [
            [
                'id'       => 'high_interest_debt_priority',
                'priority' => 1,
                'condition' => fn () => $this->user->debts()
                    ->where('interest_rate', '>', 15)->exists(),
                'message'  => function () {
                    $debt = $this->user->debts()
                        ->where('interest_rate', '>', 15)
                        ->orderByDesc('interest_rate')
                        ->first();
                    return "Dette à {$debt->interest_rate}% détectée ({$debt->label}). "
                         . "Priorité : la rembourser avant d'épargner ailleurs.";
                },
            ],
            [
                'id'       => 'late_tontine_contribution',
                'priority' => 1,
                'condition' => fn () => count($this->calculator->getLateTontineContributions()) > 0,
                'message'  => function () {
                    $count = count($this->calculator->getLateTontineContributions());
                    return "{$count} cotisation(s) tontine en retard. À régulariser rapidement.";
                },
            ],
            [
                'id'       => 'no_emergency_fund',
                'priority' => $this->getEmergencyFundPriority(),
                'condition' => function () {
                    $goal = $this->user->financialGoals()
                        ->whereRaw('LOWER(label) LIKE ?', ['%urgence%'])
                        ->first();
                    if (!$goal) return false;
                    return $goal->current_amount < $this->calculator->getSafeIncomeBaseline();
                },
                'message'  => function () {
                    $base   = "Tu n'as pas encore un mois de revenu de côté en réserve.";
                    $suffix = match ($this->user->profile?->budget_struggle_frequency) {
                        'often'     => " Vu que tu boucles souvent difficilement le mois, c'est ta priorité absolue.",
                        'sometimes' => " C'est important pour éviter les situations difficiles.",
                        default     => " C'est une bonne base avant d'autres projets.",
                    };
                    return $base . $suffix;
                },
            ],
            [
                'id'       => 'tontine_payout_incoming',
                'priority' => 2,
                'condition' => fn () => $this->calculator->getUpcomingTontinePayout() !== null,
                'message'  => function () {
                    $payout = $this->calculator->getUpcomingTontinePayout();
                    return "Réception tontine dans {$payout['days_until']} jours : "
                         . number_format($payout['amount'], 0, ',', ' ')
                         . " FCFA. Pense à planifier sa répartition.";
                },
            ],
            [
                'id'       => 'budget_threshold_alert',
                'priority' => 3,
                'condition' => fn () => $spendingPercent >= $this->getOverspendingThreshold()
                    && $this->calculator->getRealRemainingBudget() > 0,
                'message'  => function () use ($spendingPercent) {
                    $pct    = round($spendingPercent * 100);
                    $prefix = match ($this->user->profile?->spending_tendency) {
                        'spends_quickly' => "Attention — tu dépenses vite.",
                        'saves'          => "Inhabituel pour toi —",
                        default          => "",
                    };
                    return trim("{$prefix} {$pct}% de ton budget variable consommé ce mois-ci.");
                },
            ],
            [
                'id'       => 'overspending_category',
                'priority' => 4,
                'condition' => fn () => count(
                    $this->calculator->getOverspendingCategories(
                        $this->getCategoryOverspendingMultiplier()
                    )
                ) > 0,
                'message'  => function () {
                    $over = $this->calculator->getOverspendingCategories(
                        $this->getCategoryOverspendingMultiplier()
                    )[0];
                    return "{$over['category']} : "
                         . number_format($over['current'], 0, ',', ' ')
                         . " FCFA dépensés, {$over['percent_over']}% au-dessus de ta moyenne.";
                },
            ],
            [
                'id'       => 'allowance_cost_visibility',
                'priority' => 5,
                'condition' => function () {
                    return $this->user->dependents()->get()
                        ->sum(fn ($d) => $d->monthly_allowance_cost) > 0;
                },
                'message'  => function () {
                    $total   = $this->user->dependents()->get()
                        ->sum(fn ($d) => $d->monthly_allowance_cost);
                    $income  = $this->calculator->getSafeIncomeBaseline();
                    $percent = $income > 0 ? round(($total / $income) * 100) : 0;
                    return "Argent de poche pour les enfants : environ "
                         . number_format($total, 0, ',', ' ')
                         . " FCFA ce mois-ci ({$percent}% de ton revenu).";
                },
            ],
            [
                'id'       => 'goal_deadline_approaching',
                'priority' => 3,
                'condition' => function () {
                    return $this->user->financialGoals()
                        ->whereNotNull('target_date')
                        ->whereRaw('target_date <= DATE_ADD(NOW(), INTERVAL 90 DAY)')
                        ->whereColumn('current_amount', '<', 'target_amount')
                        ->exists();
                },
                'message'  => function () {
                    $goal = $this->user->financialGoals()
                        ->whereNotNull('target_date')
                        ->whereRaw('target_date <= DATE_ADD(NOW(), INTERVAL 90 DAY)')
                        ->whereColumn('current_amount', '<', 'target_amount')
                        ->orderBy('target_date')
                        ->first();

                    $daysLeft  = (int) now()->diffInDays($goal->target_date);
                    $remaining = $goal->target_amount - $goal->current_amount;
                    $perMonth  = $daysLeft > 0
                        ? round($remaining / ($daysLeft / 30))
                        : $remaining;

                    return "Objectif \"{$goal->label}\" dans {$daysLeft} jours. "
                         . "Il manque " . number_format($remaining, 0, ',', ' ')
                         . " FCFA — soit environ " . number_format($perMonth, 0, ',', ' ')
                         . " FCFA/mois pour y arriver.";
                },
            ],
            [
                'id'       => 'back_to_school_alert',
                'priority' => 3,
                'condition' => function () {
                    return $this->user->dependents()
                        ->where('relation', 'child')
                        ->where('is_schooled', true)
                        ->exists()
                        && now()->month >= 7
                        && now()->month <= 9;
                },
                'message'  => fn () =>
                    "La rentrée scolaire approche. Anticipe les frais de scolarité "
                    . "et fournitures pour éviter les dépenses imprévues.",
            ],
        ];
    }

    public function evaluate(): array
    {
        $triggered = [];

        foreach ($this->rules() as $rule) {
            try {
                if (($rule['condition'])()) {
                    $triggered[] = [
                        'rule_id'  => $rule['id'],
                        'priority' => $rule['priority'],
                        'message'  => ($rule['message'])(),
                    ];
                }
            } catch (\Exception $e) {
                Log::error("RecommendationEngine rule {$rule['id']} failed: " . $e->getMessage());
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
                ->where(fn ($q) => $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
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