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

        $calculator    = new FinancialCalculatorService($user);
        $recommender   = new RecommendationEngineService($user);
        $healthService = new FinancialHealthScoreService($user);

        $healthScore  = $healthService->calculate();
        $healthStatus = $healthService->getStatus($healthScore);

        $healthScoreDetail = [
            'income_stability'   => $healthService->getIncomeStabilityScore(),
            'debt_level'         => $healthService->getDebtLevelScore(),
            'emergency_fund'     => $healthService->getEmergencyFundScore(),
            'tontine_regularity' => $healthService->getTontineRegularityScore(),
        ];

        $goals = $user->financialGoals()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($g) => [
                'id'               => $g->id,
                'label'            => $g->label,
                'target_amount'    => $g->target_amount,
                'current_amount'   => $g->current_amount,
                'target_date'      => $g->target_date,
                'progress_percent' => $g->progress_percent,
            ]);

        $safeIncome              = $calculator->getSafeIncomeBaseline();
        $totalCharges            = $calculator->getTotalMonthlyFixedCharges(); // affichage uniquement
        $resteAVivre             = $calculator->getResteAVivre();
        $variableSpending        = $calculator->getCurrentMonthVariableSpending(); // toutes les dépenses
        $transactionsIn          = $calculator->getCurrentMonthTransactionsIn();
        $realRemaining           = $calculator->getRealRemainingBudget();
        $fixedChargesConsumption = $calculator->getFixedChargesConsumption();

        return Inertia::render('Dashboard', [
            'safeIncome'              => $safeIncome,
            'totalCharges'            => $totalCharges,
            'resteAVivre'             => $resteAVivre,
            'variableSpending'        => $variableSpending,
            'transactionsIn'          => $transactionsIn,
            'realRemaining'           => $realRemaining,
            'currentMonthIn'          => $transactionsIn,
            'healthScore'             => $healthScore,
            'healthStatus'            => $healthStatus,
            'healthScoreDetail'       => $healthScoreDetail,
            'recommendations'         => $recommender->getTopRecommendations(3),
            'upcomingTontinePayout'   => $calculator->getUpcomingTontinePayout(),
            'spendingByCategory'      => $calculator->getCurrentMonthSpendingByCategory(),
            'daysLeftInMonth'         => $calculator->getDaysLeftInFinancialMonth(),
            'debts'                   => $user->debts()->where('remaining_amount', '>', 0)->orderByDesc('interest_rate')->get(),
            'fixedChargesConsumption' => $fixedChargesConsumption,
            'isSnapshotMode'          => $calculator->isSnapshotMode(),
            'fixedChargesSurplus'     => 0, // plus utilisé — gardé pour compatibilité Vue
            'goals'                   => $goals,
            'employmentType'          => $user->profile?->employment_type,
            'salaryDay'               => $user->profile?->salary_day,
            'salaryDeclarationWindow' => $calculator->isSalaryDeclarationWindow(),
            'nextPayday'              => $calculator->getNextPayday()->toDateString(),
        ]);
    }

    /**
     * Bug 2 — Déclaration manuelle du salaire reçu.
     * L'utilisateur confirme la réception (le salaire peut arriver en retard) :
     * on enregistre l'entrée, ce qui alimente le revenu de référence et démarre
     * le nouveau cycle. Aucune réinitialisation automatique.
     */
    public function declareSalary(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user()->load([
            'profile', 'incomeSources', 'fixedCharges', 'transactions',
        ]);

        $calculator = new FinancialCalculatorService($user);

        // Report du solde restant du cycle qui se termine (uniquement s'il est positif).
        $leftover = max(0, $calculator->getRealRemainingBudget());

        // Enregistre l'entrée (alimente le revenu de référence + sert de "salaire déclaré").
        $user->incomeRecords()->create([
            'amount'      => $validated['amount'],
            'received_at' => now(),
        ]);

        // Nouveau reste-à-vivre = salaire déclaré + report éventuel.
        // Le snapshot force getResteAVivre() à repartir de ce montant pour le nouveau cycle.
        $user->profile->update([
            'current_month_remaining' => $validated['amount'] + $leftover,
            'remaining_snapshot_date' => now(),
        ]);

        $message = $leftover > 0
            ? 'Salaire enregistré. Solde reporté : ' . number_format($leftover, 0, ',', ' ') . ' FCFA.'
            : 'Salaire enregistré. Nouveau cycle démarré.';

        return back()->with('success', $message);
    }
}