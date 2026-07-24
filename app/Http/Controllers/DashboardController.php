<?php

namespace App\Http\Controllers;

use App\Http\Services\FinancialCalculatorService;
use App\Http\Services\FinancialHealthScoreService;
use App\Http\Services\RecommendationEngineService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load([
            'profile',
            'dependents',
            'incomeSources',
            'fixedCharges',
            'debts',
            'financialGoals',
            'tontineGroups.cycles.contribution',
        ]);

        $calculator  = new FinancialCalculatorService($user);
        $recommender = new RecommendationEngineService($user);
        $healthService = new FinancialHealthScoreService($user);
        $healthScore = $healthService->calculate();
        $healthStatus = $healthService->getStatus($healthScore);

        // Détail du score de santé
        $healthScoreDetail = [
            'income_stability' => $healthService->getIncomeStabilityScore(),
            'debt_level' => $healthService->getDebtLevelScore(),
            'emergency_fund' => $healthService->getEmergencyFundScore(),
            'tontine_regularity' => $healthService->getTontineRegularityScore(),
        ];

        // Objectifs actifs
        $goals = $user->financialGoals()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'label' => $g->label,
                'target_amount' => $g->target_amount,
                'current_amount' => $g->current_amount,
                'target_date' => $g->target_date,
                'progress_percent' => $g->progress_percent,
            ]);

        $safeIncome            = $calculator->getSafeIncomeBaseline();
        $totalCharges          = $calculator->getTotalMonthlyFixedCharges();
        $resteAVivre           = $calculator->getResteAVivre();
        $variableSpending      = $calculator->getCurrentMonthVariableSpending();
        $transactionsIn        = $calculator->getCurrentMonthTransactionsIn();
        $fixedChargesConsumption = $calculator->getFixedChargesConsumption();
        $healthScore           = $healthService->calculate();

        return Inertia::render('Dashboard', [
            'safeIncome'              => $safeIncome,
            'totalCharges'            => $totalCharges,
            'resteAVivre'             => $resteAVivre,
            'variableSpending'        => $variableSpending,
            'transactionsIn'          => $transactionsIn,
            'realRemaining'           => $resteAVivre - $variableSpending + $transactionsIn,
            'currentMonthIn'          => $transactionsIn,
            'healthScore'             => $healthScore,
            'healthStatus'            => $healthService->getStatus($healthScore),
            'recommendations'         => $recommender->getTopRecommendations(3),
            'upcomingTontinePayout'   => $calculator->getUpcomingTontinePayout(),
            'spendingByCategory'      => $calculator->getCurrentMonthSpendingByCategory(),
            'daysLeftInMonth'         => now()->daysInMonth - now()->day,
            'debts' => $user->debts()->where('remaining_amount', '>', 0)->orderByDesc('interest_rate')->get(),
            'fixedChargesConsumption' => $fixedChargesConsumption,
            'isSnapshotMode' => $calculator->isSnapshotMode(),
            'fixedChargesSurplus' => $calculator->getFixedChargesSurplus(),
            'healthScoreDetail' => $healthScoreDetail,
            'goals' => $goals,
            'daysLeftInMonth'       => $calculator->getDaysLeftInFinancialMonth(),
            'employmentType'        => $user->profile?->employment_type,
            'salaryDay'             => $user->profile?->salary_day,
        ]);
    }
}