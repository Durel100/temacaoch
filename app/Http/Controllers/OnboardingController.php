<?php

namespace App\Http\Controllers;

use App\Models\Dependent;
use App\Models\FinancialGoal;
use App\Models\IncomeSource;
use App\Models\UserProfile;
use App\Models\FixedCharge;
use App\Models\Debt;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * Étape 1 — Situation personnelle
     */
    public function showPersonalInfo()
    {
        return Inertia::render('Onboarding/PersonalInfo');
    }

    public function storePersonalInfo(Request $request)
    {
        $validated = $request->validate([
            'marital_status'              => 'required|in:single,married,in_relationship,divorced,widowed',
            'employment_type'             => 'required|in:salaried,non_salaried',
            'spouse_contributes'          => 'nullable|boolean',
            'spouse_monthly_contribution' => 'nullable|numeric|min:0',
            'shared_fixed_charges'        => 'nullable|boolean',
            'dependents'                  => 'array',
            'dependents.*.relation'       => 'required|in:child,parent,other',
            'dependents.*.age_range'      => 'nullable|in:0-5,6-12,13-18,adult',
            'dependents.*.is_schooled'    => 'boolean',
            'dependents.*.allowance_amount'     => 'nullable|numeric|min:0',
            'dependents.*.allowance_frequency'  => 'nullable|in:daily,weekly,monthly',
            'dependents.*.allowance_managed_by' => 'nullable|in:parent,child',
        ]);

        $user = $request->user();

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'marital_status'              => $validated['marital_status'],
                'employment_type'             => $validated['employment_type'],
                'spouse_contributes'          => $validated['spouse_contributes'] ?? null,
                'spouse_monthly_contribution' => $validated['spouse_monthly_contribution'] ?? null,
                'shared_fixed_charges'        => $validated['shared_fixed_charges'] ?? null,
            ]
        );

        $user->dependents()->delete();
        foreach ($validated['dependents'] ?? [] as $dependent) {
            $user->dependents()->create([
                'relation'             => $dependent['relation'],
                'age_range'            => $dependent['age_range'] ?? null,
                'is_schooled'          => $dependent['relation'] === 'child'
                    ? ($dependent['is_schooled'] ?? false)
                    : false,
                'allowance_amount'     => ($dependent['relation'] !== 'parent' && !empty($dependent['allowance_amount']))
                    ? $dependent['allowance_amount']
                    : null,
                'allowance_frequency'  => ($dependent['relation'] !== 'parent' && !empty($dependent['allowance_frequency']))
                    ? $dependent['allowance_frequency']
                    : null,
                'allowance_managed_by' => ($dependent['relation'] !== 'parent' && !empty($dependent['allowance_managed_by']))
                    ? $dependent['allowance_managed_by']
                    : null,
            ]);
        }

        return redirect()->route('onboarding.income');
    }

    /**
     * Étape 2 — Revenus
     */
    public function showIncome()
    {
        return Inertia::render('Onboarding/Income');
    }

    public function storeIncome(Request $request)
    {
        $validated = $request->validate([
            'income_sources'             => 'required|array|min:1',
            'income_sources.*.type'      => 'required|in:salary,irregular_business,family_allowance,scholarship,other',
            'income_sources.*.frequency' => 'required|in:monthly,weekly,biweekly,irregular',
            'income_sources.*.amount'    => 'required|numeric|min:0',
            'income_sources.*.label'     => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $user->incomeSources()->delete();

        foreach ($validated['income_sources'] as $source) {
            $user->incomeSources()->create($source);
        }

        return redirect()->route('onboarding.charges');
    }

    /**
     * Étape 3 — Charges fixes
     */
    public function showCharges()
    {
        return Inertia::render('Onboarding/Charges');
    }

    public function storeCharges(Request $request)
    {
        $validated = $request->validate([
            'fixed_charges'             => 'array',
            'fixed_charges.*.label'     => 'required|string|max:255',
            'fixed_charges.*.amount'    => 'required|numeric|min:0',
            'fixed_charges.*.frequency' => 'required|in:monthly,weekly,yearly',
        ]);

        $user = $request->user();
        $user->fixedCharges()->delete();

        foreach ($validated['fixed_charges'] ?? [] as $charge) {
            $user->fixedCharges()->create($charge);
        }

        return redirect()->route('onboarding.current-situation');
    }

    /**
     * Étape 4 — Situation actuelle ce mois
     */
    public function showCurrentSituation(Request $request)
    {
        $user    = $request->user();
        $profile = $user->profile;

        return Inertia::render('Onboarding/CurrentSituation', [
            'employment_type' => $profile?->employment_type ?? 'non_salaried',
        ]);
    }

    public function storeCurrentSituation(Request $request)
    {
        $validated = $request->validate([
            'salary_day'                          => 'nullable|integer|min:1|max:31',
            'salary_already_received'             => 'nullable|boolean',
            'current_month_remaining'             => 'nullable|numeric|min:0',
            'remaining_fixed_charges_this_month'  => 'nullable|numeric|min:0',
        ]);

        UserProfile::where('user_id', $request->user()->id)->update([
            'salary_day'                         => $validated['salary_day'] ?? null,
            'current_month_remaining'            => $validated['current_month_remaining'] ?? null,
            'remaining_fixed_charges_this_month' => $validated['remaining_fixed_charges_this_month'] ?? 0,
            'remaining_snapshot_date'            => $validated['current_month_remaining'] !== null
                ? now()->toDateString()
                : null,
            // Bug 2 : "Oui, déjà reçu" confirme la paie de ce cycle → le bouton
            // "J'ai reçu mon salaire" ne s'affichera pas. "Non" laisse null → le
            // bouton apparaîtra le jour de la paie.
            'salary_received_at'                 => ($validated['salary_already_received'] ?? false)
                ? now()
                : null,
        ]);

        return redirect()->route('onboarding.habits');
    }

    /**
     * Étape 5 — Habitudes financières
     */
    public function showHabits()
    {
        return Inertia::render('Onboarding/Habits');
    }

    public function storeHabits(Request $request)
    {
        $validated = $request->validate([
            'spending_tendency'          => 'required|in:spends_quickly,saves,depends',
            'budget_struggle_frequency'  => 'required|in:often,sometimes,rarely',
            'budget_preference'          => 'required|in:strict,flexible',
        ]);

        UserProfile::where('user_id', $request->user()->id)->update($validated);

        return redirect()->route('onboarding.debts');
    }

    /**
     * Étape 6 — Dettes
     */
    public function showDebts()
    {
        return Inertia::render('Onboarding/Debts');
    }

    public function storeDebts(Request $request)
    {
        $validated = $request->validate([
            'debts'                    => 'array',
            'debts.*.label'            => 'required|string|max:255',
            'debts.*.total_amount'     => 'required|numeric|min:0',
            'debts.*.remaining_amount' => 'required|numeric|min:0',
            'debts.*.interest_rate'    => 'nullable|numeric|min:0',
            'debts.*.monthly_payment'  => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $user->debts()->delete();

        foreach ($validated['debts'] ?? [] as $debt) {
            $user->debts()->create($debt);
        }

        return redirect()->route('onboarding.goals');
    }

    /**
     * Étape 7 — Objectifs
     */
    public function showGoals()
    {
        return Inertia::render('Onboarding/Goals');
    }

    public function storeGoals(Request $request)
    {
        $validated = $request->validate([
            'financial_goals'                  => 'array',
            'financial_goals.*.label'          => 'required|string|max:255',
            'financial_goals.*.target_amount'  => 'required|numeric|min:0',
            'financial_goals.*.target_date'    => 'nullable|date',
        ]);

        $user = $request->user();
        $user->financialGoals()->delete();

        foreach ($validated['financial_goals'] ?? [] as $goal) {
            $user->financialGoals()->create([
                'label'          => $goal['label'],
                'target_amount'  => $goal['target_amount'],
                'current_amount' => 0,
                'target_date'    => $goal['target_date'] ?? null,
            ]);
        }

        return redirect()->route('onboarding.summary');
    }

    /**
     * Étape 8 — Récapitulatif
     */
    public function showSummary(Request $request)
    {
        $user = $request->user();

        if (!$user->onboarding_completed_at) {
            $user->update(['onboarding_completed_at' => now()]);
        }

        $user->load([
            'profile', 'dependents', 'incomeSources',
            'fixedCharges', 'debts', 'financialGoals', 'tontineGroups',
        ]);

        $calculator = new FinancialCalculatorService($user);

        return Inertia::render('Onboarding/Summary', [
            'resteAVivre'   => $calculator->getResteAVivre(),
            'totalCharges'  => $calculator->getTotalMonthlyFixedCharges(),
            'safeIncome'    => $calculator->getSafeIncomeBaseline(),
            'employmentType' => $user->profile?->employment_type,
            'spouseContributes' => $user->profile?->spouse_contributes ?? false,
            'spouseContribution' => (float) $user->profile->spouse_monthly_contribution,
        ]);
    }
}