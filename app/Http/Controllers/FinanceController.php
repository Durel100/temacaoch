<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\FixedCharge;
use App\Models\Category;
use App\Models\Transaction;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load([
            'debts',
            'fixedCharges',
        ]);

        $activeDebts = $user->debts()
            ->where('remaining_amount', '>', 0)
            ->orderByDesc('interest_rate')
            ->get();

        $settledDebts = $user->debts()
            ->where('remaining_amount', '<=', 0)
            ->orderByDesc('updated_at')
            ->get();

        $activeCharges = $user->fixedCharges()
            ->where('is_active', true)
            ->orderBy('label')
            ->get();

        $inactiveCharges = $user->fixedCharges()
            ->where('is_active', false)
            ->orderByDesc('updated_at')
            ->get();

        $calculator         = new FinancialCalculatorService($user);
        $chargesConsumption = $calculator->getFixedChargesConsumption();

        return Inertia::render('Finances/Index', [
            'activeDebts'          => $activeDebts,
            'settledDebts'         => $settledDebts,
            'activeCharges'        => $activeCharges,
            'inactiveCharges'      => $inactiveCharges,
            'chargesConsumption'   => collect($chargesConsumption)->keyBy('id'),
            'totalActiveDebt'      => $activeDebts->sum('remaining_amount'),
            'totalMonthlyPayments' => $activeDebts->sum('monthly_payment'),
        ]);
    }

    // ── Dettes ──────────────────────────────────────────────────────────

    public function storeDette(Request $request)
    {
        $validated = $request->validate([
            'label'            => 'required|string|max:255',
            'total_amount'     => 'required|numeric|min:1',
            'remaining_amount' => 'required|numeric|min:0',
            'interest_rate'    => 'nullable|numeric|min:0|max:100',
            'monthly_payment'  => 'nullable|numeric|min:0',
        ]);

        $request->user()->debts()->create($validated);

        return back()->with('success', 'Dette ajoutée.');
    }

    public function repayDette(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user         = $request->user();
        $repayAmount  = min($validated['amount'], $debt->remaining_amount);
        $newRemaining = round($debt->remaining_amount - $repayAmount, 2);

        // Mettre à jour le montant restant de la dette
        $debt->update(['remaining_amount' => $newRemaining]);

        if ($debt->label === 'Découvert budget') {
            // ── Dette de découvert : transaction "in" compensatoire ──────────
            // Le découvert a déjà créé une réduction du budget via syncOverdraftDebt.
            // Le remboursement crée une transaction entrante pour corriger le budget.
            $category = Category::firstOrCreate(
                ['name' => 'Remboursement découvert', 'user_id' => null],
                [
                    'icon'              => 'wallet',
                    'default_direction' => 'in',
                    'is_system'         => true,
                    'translation_key'   => 'cat_overdraft_repay',
                ]
            );

            Transaction::create([
                'user_id'       => $user->id,
                'amount'        => $repayAmount,
                'direction'     => 'in',
                'category_id'   => $category->id,
                'transacted_at' => now(),
                'source'        => 'manual_custom',
                'note'          => 'Remboursement découvert budget',
            ]);

            $this->syncAfterRepay($user);

        } else {
            // ── Dette normale : transaction "out" pour débiter le budget ─────
            // Le remboursement d'une dette normale sort de l'argent du compte.
            $category = Category::firstOrCreate(
                ['name' => 'Remboursement dette', 'user_id' => null],
                [
                    'icon'              => 'wallet',
                    'default_direction' => 'out',
                    'is_system'         => true,
                    'translation_key'   => 'cat_debt_repay',
                ]
            );

            Transaction::create([
                'user_id'       => $user->id,
                'amount'        => $repayAmount,
                'direction'     => 'out',
                'category_id'   => $category->id,
                'transacted_at' => now(),
                'source'        => 'manual_custom',
                'note'          => 'Remboursement : ' . $debt->label,
            ]);
        }

        $message = $newRemaining <= 0
            ? 'Dette soldée — félicitations ! 🎉'
            : 'Remboursement enregistré.';

        return back()->with('success', $message);
    }

    /**
     * Resynchronise la dette de découvert après un remboursement
     */
    private function syncAfterRepay($user): void
    {
        $user->load([
            'profile', 'dependents', 'incomeSources',
            'fixedCharges', 'debts', 'financialGoals', 'tontineGroups',
        ]);

        $calculator    = new FinancialCalculatorService($user);
        $realRemaining = $calculator->getRealRemainingBudget();

        if ($realRemaining >= 0) {
            // Budget repassé positif → supprimer la dette de découvert
            Debt::where('user_id', $user->id)
                ->where('label', 'Découvert budget')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->delete();
        } else {
            // Budget encore négatif → mettre à jour le montant restant
            Debt::where('user_id', $user->id)
                ->where('label', 'Découvert budget')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->update(['remaining_amount' => round(abs($realRemaining), 2)]);
        }
    }

    public function destroyDette(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) abort(403);
        $debt->delete();
        return back()->with('success', 'Dette supprimée.');
    }

    // ── Charges fixes ────────────────────────────────────────────────────

    public function storeCharge(Request $request)
    {
        $validated = $request->validate([
            'label'     => 'required|string|max:255',
            'amount'    => 'required|numeric|min:1',
            'frequency' => 'required|in:monthly,weekly,yearly',
        ]);

        $validated['is_active'] = true;
        $request->user()->fixedCharges()->create($validated);

        return back()->with('success', 'Charge fixe ajoutée.');
    }

    public function toggleCharge(Request $request, FixedCharge $charge)
    {
        if ($charge->user_id !== $request->user()->id) abort(403);
        $charge->update(['is_active' => !$charge->is_active]);
        $message = $charge->is_active ? 'Charge réactivée.' : 'Charge désactivée.';
        return back()->with('success', $message);
    }

    public function destroyCharge(Request $request, FixedCharge $charge)
    {
        if ($charge->user_id !== $request->user()->id) abort(403);
        $charge->delete();
        return back()->with('success', 'Charge supprimée.');
    }

    public function payCharge(Request $request, FixedCharge $charge)
    {
        if ($charge->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();

        // Trouver la catégorie correspondante ou en créer une
        $category = \App\Models\Category::where('name', $charge->label)
            ->whereNull('user_id')
            ->first()
            ?? \App\Models\Category::firstOrCreate(
                ['name' => $charge->label, 'user_id' => $user->id],
                [
                    'icon'              => 'wallet',
                    'default_direction' => 'out',
                    'is_system'         => false,
                ]
            );

        // Créer la transaction
        \App\Models\Transaction::create([
            'user_id'        => $user->id,
            'amount'         => $validated['amount'],
            'direction'      => 'out',
            'category_id'    => $category->id,
            'fixed_charge_id'=> $charge->id,
            'transacted_at'  => now(),
            'source'         => 'manual_custom',
            'note'           => 'Paiement : ' . $charge->label,
        ]);

        // Sync découvert
        $user->load([
            'profile', 'dependents', 'incomeSources',
            'fixedCharges', 'debts', 'financialGoals', 'tontineGroups',
        ]);
        $calculator    = new \App\Http\Services\FinancialCalculatorService($user);
        $realRemaining = $calculator->getRealRemainingBudget();

        $existingDebt = \App\Models\Debt::where('user_id', $user->id)
            ->where('label', 'Découvert budget')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->first();

        if ($realRemaining >= 0) {
            $existingDebt?->delete();
        } else {
            $amount = round(abs($realRemaining), 2);
            if ($existingDebt) {
                $existingDebt->update([
                    'remaining_amount' => $amount,
                    'total_amount'     => max($existingDebt->total_amount, $amount),
                ]);
            } else {
                \App\Models\Debt::create([
                    'user_id'          => $user->id,
                    'label'            => 'Découvert budget',
                    'total_amount'     => $amount,
                    'remaining_amount' => $amount,
                ]);
            }
        }

        return back()->with('success', 'Paiement enregistré — déduit de ton budget.');
    }
}