<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\FinancialHealthScore;

class FinancialHealthScoreService
{
    protected FinancialCalculatorService $calculator;

    public function __construct(private User $user)
    {
        $this->calculator = new FinancialCalculatorService($user);
    }

    /**
     * Score composite sur 100, basé sur 4 facteurs simples
     */
    public function calculate(): int
    {
        $score = 0;

        // 1. Stabilité du revenu (25 pts) — écart entre min et max des 3 derniers mois
        $score += $this->scoreIncomeStability();

        // 2. Niveau d'endettement (25 pts) — ratio dette/revenu
        $score += $this->scoreDebtLevel();

        // 3. Présence d'épargne d'urgence (25 pts)
        $score += $this->scoreEmergencyFund();

        // 4. Régularité des cotisations tontine (25 pts)
        $score += $this->scoreTontineRegularity();

        return min(100, max(0, $score));
    }

    protected function scoreIncomeStability(): int
    {
        $baseline = $this->calculator->getSafeIncomeBaseline();
        $charges = $this->calculator->getTotalMonthlyFixedCharges();

        if ($baseline <= 0) return 0;

        $ratio = $charges / $baseline;

        return match (true) {
            $ratio < 0.5 => 25,
            $ratio < 0.7 => 18,
            $ratio < 0.9 => 10,
            default => 0,
        };
    }

    protected function scoreDebtLevel(): int
    {
        $totalDebt = $this->user->debts()->sum('remaining_amount');
        $income = $this->calculator->getSafeIncomeBaseline();

        if ($income <= 0) return $totalDebt > 0 ? 0 : 25;

        $ratio = $totalDebt / $income;

        return match (true) {
            $ratio == 0 => 25,
            $ratio < 1 => 18,
            $ratio < 3 => 10,
            default => 0,
        };
    }

    protected function scoreEmergencyFund(): int
    {
        $goal = $this->user->financialGoals()->where('label', 'like', '%urgence%')->first();
        $baseline = $this->calculator->getSafeIncomeBaseline();

        if (!$goal || $baseline <= 0) return 0;

        $monthsCovered = $goal->current_amount / $baseline;

        return match (true) {
            $monthsCovered >= 3 => 25,
            $monthsCovered >= 1 => 15,
            $monthsCovered > 0 => 8,
            default => 0,
        };
    }

    protected function scoreTontineRegularity(): int
    {
        $contributions = \App\Models\TontineContribution::whereHas('cycle.group', function ($q) {
                $q->where('user_id', $this->user->id);
            })
            ->where('status', '!=', 'pending')
            ->get();

        if ($contributions->isEmpty()) return 25; // pas de tontine = neutre, pas pénalisé

        $lateCount = $contributions->where('status', 'late')->count();
        $lateRatio = $lateCount / $contributions->count();

        return match (true) {
            $lateRatio === 0.0 => 25,
            $lateRatio < 0.2 => 15,
            $lateRatio < 0.5 => 8,
            default => 0,
        };
    }

    public function getStatus(int $score): string
    {
        return match (true) {
            $score >= 70 => 'stable',
            $score >= 40 => 'to_watch',
            default => 'fragile',
        };
    }

    public function calculateAndStore(): FinancialHealthScore
    {
        $score = $this->calculate();

        return FinancialHealthScore::create([
            'user_id' => $this->user->id,
            'score' => $score,
            'status' => $this->getStatus($score),
            'calculated_for_month' => now()->startOfMonth(),
        ]);
    }

    public function getIncomeStabilityScore(): int
    {
        return $this->scoreIncomeStability();
    }

    public function getDebtLevelScore(): int
    {
        return $this->scoreDebtLevel();
    }

    public function getEmergencyFundScore(): int
    {
        return $this->scoreEmergencyFund();
    }

    public function getTontineRegularityScore(): int
    {
        return $this->scoreTontineRegularity();
    }
}