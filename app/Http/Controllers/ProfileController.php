<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Models\IncomeSource;
use App\Models\Dependent;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load([
            'profile',
            'dependents',
            'incomeSources',
            'fixedCharges',
            'debts',
        ]);

        $calculator = new FinancialCalculatorService($user);

        return Inertia::render('Profile/Edit', [
            'user'    => $user,
            'profile' => $user->profile,
            'dependents'    => $user->dependents,
            'incomeSources' => $user->incomeSources,
            'summary' => [
                'safeIncome'   => $calculator->getSafeIncomeBaseline(),
                'totalCharges' => $calculator->getTotalMonthlyFixedCharges(),
                'resteAVivre'  => $calculator->getResteAVivre(),
            ],
            'activeDebtsCount'   => $user->debts()->where('remaining_amount', '>', 0)->count(),
            'activeChargesCount' => $user->fixedCharges()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Mettre à jour les infos de base (nom, email)
     */
    public function updateInfo(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);

        return back()->with('success', 'Informations mises à jour.');
    }

    /**
     * Mettre à jour le mot de passe
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Mot de passe mis à jour.');
    }

    /**
     * Mettre à jour la situation personnelle
     */
    public function updatePersonal(Request $request)
    {
        $validated = $request->validate([
            'marital_status'              => 'required|in:single,married,in_relationship,divorced,widowed',
            'employment_type'             => 'required|in:salaried,non_salaried',
            'spouse_contributes'          => 'nullable|boolean',
            'spouse_monthly_contribution' => 'nullable|numeric|min:0',
            'shared_fixed_charges'        => 'nullable|boolean',
            'salary_day'                  => 'nullable|integer|min:1|max:31',
        ]);

        UserProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return back()->with('success', 'Situation personnelle mise à jour.');
    }

    /**
     * Mettre à jour les habitudes financières
     */
    public function updateHabits(Request $request)
    {
        $validated = $request->validate([
            'spending_tendency'         => 'required|in:spends_quickly,saves,depends',
            'budget_struggle_frequency' => 'required|in:often,sometimes,rarely',
            'budget_preference'         => 'required|in:strict,flexible',
        ]);

        UserProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return back()->with('success', 'Préférences de coaching mises à jour.');
    }

    /**
     * Mettre à jour les sources de revenus
     */
    public function updateIncome(Request $request)
    {
        $validated = $request->validate([
            'income_sources'             => 'required|array|min:1',
            'income_sources.*.id'        => 'nullable|integer',
            'income_sources.*.type'      => 'required|in:salary,irregular_business,family_allowance,scholarship,other',
            'income_sources.*.frequency' => 'required|in:monthly,weekly,biweekly,irregular',
            'income_sources.*.amount'    => 'required|numeric|min:0',
            'income_sources.*.label'     => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $user->incomeSources()->delete();

        foreach ($validated['income_sources'] as $source) {
            $user->incomeSources()->create([
                'type'      => $source['type'],
                'frequency' => $source['frequency'],
                'amount'    => $source['amount'],
                'label'     => $source['label'] ?? null,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Revenus mis à jour.');
    }

    /**
     * Mettre à jour les personnes à charge
     */
    public function updateDependents(Request $request)
    {
        $validated = $request->validate([
            'dependents'                        => 'array',
            'dependents.*.relation'             => 'required|in:child,parent,other',
            'dependents.*.age_range'            => 'nullable|in:0-5,6-12,13-18,adult',
            'dependents.*.is_schooled'          => 'boolean',
            'dependents.*.allowance_amount'     => 'nullable|numeric|min:0',
            'dependents.*.allowance_frequency'  => 'nullable|in:daily,weekly,monthly',
            'dependents.*.allowance_managed_by' => 'nullable|in:parent,child',
        ]);

        $user = $request->user();
        $user->dependents()->delete();

        foreach ($validated['dependents'] ?? [] as $dep) {
            $user->dependents()->create([
                'relation'             => $dep['relation'],
                'age_range'            => $dep['age_range'] ?? null,
                'is_schooled'          => $dep['relation'] === 'child' ? ($dep['is_schooled'] ?? false) : false,
                'allowance_amount'     => ($dep['relation'] !== 'parent' && !empty($dep['allowance_amount'])) ? $dep['allowance_amount'] : null,
                'allowance_frequency'  => ($dep['relation'] !== 'parent' && !empty($dep['allowance_frequency'])) ? $dep['allowance_frequency'] : null,
                'allowance_managed_by' => ($dep['relation'] !== 'parent' && !empty($dep['allowance_managed_by'])) ? $dep['allowance_managed_by'] : null,
            ]);
        }

        return back()->with('success', 'Personnes à charge mises à jour.');
    }

    /**
     * Supprimer le compte
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Mettre à jour la langue
     */
    public function updateLocale(Request $request)
    {
        $validated = $request->validate([
            'locale' => 'required|in:fr,en',
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        return back()->with('success', 'Langue mise à jour.');
    }
}