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

        $debt->update(['remaining_amount' => $newRemaining]);

        // Dette système (découvert) : remboursement purement manuel — on réduit
        // seulement le montant restant, aucun mouvement de budget ni suppression auto.
        // Dette normale : le remboursement sort de l'argent du budget.
        if (!$debt->is_system) {
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
     * Modifier une dette (renommer, ajuster les montants).
     * Marche aussi pour la dette de découvert système — on ne touche jamais à is_system.
     */
    public function updateDette(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'label'            => 'required|string|max:255',
            'total_amount'     => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric|min:0',
        ]);

        $debt->update(array_filter([
            'label'            => $validated['label'],
            'total_amount'     => $validated['total_amount']     ?? null,
            'remaining_amount' => $validated['remaining_amount'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Dette mise à jour.');
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

        // Sync découvert (logique centralisée — création unique, jamais de suppression auto)
        (new \App\Http\Services\FinancialCalculatorService($user))->syncOverdraftDebt();

        return back()->with('success', 'Paiement enregistré — déduit de ton budget.');
    }
}