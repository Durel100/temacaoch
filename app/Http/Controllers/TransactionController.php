<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\QuickAction;
use App\Models\Transaction;
use App\Models\Debt;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function create(Request $request)
    {
        $user         = $request->user();
        $categories   = Category::availableFor($user->id);
        $fixedCharges = $user->fixedCharges()->where('is_active', true)->get();

        $quickActions = $user->quickActions()
            ->with('category')
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->get();

        if ($quickActions->isEmpty()) {
            $quickActions = collect($this->getDefaultQuickActions($user->id, $categories));
        }

        $quickActions = $quickActions->map(function ($action) use ($fixedCharges) {
            $matchingCharge = $fixedCharges->first(function ($charge) use ($action) {
                return strtolower(trim($charge->label)) === strtolower(trim($action['label'] ?? $action->label));
            });

            if (is_array($action)) {
                $action['fixed_charge_id'] = $matchingCharge?->id;
                $action['is_fixed_charge'] = $matchingCharge !== null;
            } else {
                $action->fixed_charge_id = $matchingCharge?->id;
                $action->is_fixed_charge = $matchingCharge !== null;
            }

            return $action;
        });

        return Inertia::render('Transactions/Create', [
            'categories'   => $categories,
            'quickActions' => $quickActions,
            'fixedCharges' => $fixedCharges,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'          => 'required|numeric|min:1',
            'direction'       => 'required|in:in,out',
            'category_id'     => 'required|exists:categories,id',
            'quick_action_id' => 'nullable|exists:quick_actions,id',
            'fixed_charge_id' => 'nullable|exists:fixed_charges,id',
            'source'          => 'nullable|in:quick_action,manual_custom',
            'transacted_at'   => 'required|date',
            'note'            => 'nullable|string|max:500',
        ]);

        $validated['transacted_at'] = $validated['transacted_at'] ?? now();
        $validated['source']        = $validated['source'] ?? ($validated['quick_action_id'] ? 'quick_action' : 'manual_custom');
        $validated['user_id']       = $request->user()->id;

        if (!empty($validated['quick_action_id']) && empty($validated['fixed_charge_id'])) {
            $quickAction = QuickAction::find($validated['quick_action_id']);
            if ($quickAction?->fixed_charge_id) {
                $validated['fixed_charge_id'] = $quickAction->fixed_charge_id;
            }
        }

        Transaction::create($validated);

        if (!empty($validated['quick_action_id'])) {
            QuickAction::find($validated['quick_action_id'])?->incrementUsage();
        }

        $this->syncOverdraftDebt($request->user());

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction enregistrée.');
    }

    public function index(Request $request)
    {
        $user   = $request->user();
        $period = $request->get('period', 'month');
        $query  = $user->transactions()
            ->with('category:id,name,translation_key,icon,default_direction');

        // Filtre direction
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        // Filtre catégorie
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtre période
        $this->applyPeriodFilter($query, $period, $request);

        $transactions = $query
            ->orderByDesc('transacted_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Totaux sur la même période
        $totalsQuery = $user->transactions();
        $this->applyPeriodFilter($totalsQuery, $period, $request);
        $totals = $totalsQuery->selectRaw('
            SUM(CASE WHEN direction = "in"  THEN amount ELSE 0 END) as total_in,
            SUM(CASE WHEN direction = "out" THEN amount ELSE 0 END) as total_out,
            COUNT(*) as total_count
        ')->first();

        $categories = Category::availableFor($user->id);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'totals'       => $totals,
            'categories'   => $categories,
            'filters'      => $request->only(['direction', 'category_id', 'period', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Applique le filtre de période à une query
     */
    private function applyPeriodFilter($query, string $period, Request $request): void
    {
        match ($period) {
            'today' => $query->whereBetween('transacted_at', [
                now()->startOfDay(),   // 2026-07-23 00:00:00 Africa/Douala
                now()->endOfDay(),     // 2026-07-23 23:59:59 Africa/Douala
            ]),
            'week' => $query->whereBetween('transacted_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]),
            'year' => $query->whereYear('transacted_at', now()->year),
            'custom' => $query->whereBetween('transacted_at', [
                Carbon::parse($request->get('date_from', now()->toDateString()))
                    ->startOfDay(),
                Carbon::parse($request->get('date_to', now()->toDateString()))
                    ->endOfDay(),
            ]),
            default => $query
                ->whereMonth('transacted_at', now()->month)
                ->whereYear('transacted_at', now()->year),
        };
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) abort(403);

        $transaction->delete();
        $this->syncOverdraftDebt($request->user());

        return back()->with('success', 'Transaction supprimée.');
    }

    private function syncOverdraftDebt($user): void
    {
        $user->load([
            'profile', 'dependents', 'incomeSources',
            'fixedCharges', 'debts', 'financialGoals', 'tontineGroups',
        ]);

        $calculator    = new FinancialCalculatorService($user);
        $realRemaining = $calculator->getRealRemainingBudget();

        $existingDebt = Debt::where('user_id', $user->id)
            ->where('label', 'Découvert budget')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->first();

        if ($realRemaining >= 0) {
            $existingDebt?->delete();
            return;
        }

        $overdraftAmount = round(abs($realRemaining), 2);

        if ($existingDebt) {
            $existingDebt->update([
                'remaining_amount' => $overdraftAmount,
                'total_amount'     => max($existingDebt->total_amount, $overdraftAmount),
            ]);
        } else {
            Debt::create([
                'user_id'          => $user->id,
                'label'            => 'Découvert budget',
                'total_amount'     => $overdraftAmount,
                'remaining_amount' => $overdraftAmount,
                'interest_rate'    => null,
                'monthly_payment'  => null,
            ]);
        }
    }

    private function getDefaultQuickActions(int $userId, $categories): array
    {
        $defaults = [
            ['label' => 'Transport',             'amount' => 500,   'direction' => 'out'],
            ['label' => 'Nourriture/Marché',     'amount' => 2000,  'direction' => 'out'],
            ['label' => 'Recharge téléphonique', 'amount' => 1000,  'direction' => 'out'],
            ['label' => 'Retrait agent',          'amount' => 20000, 'direction' => 'out'],
        ];

        $quickActions = [];

        foreach ($defaults as $default) {
            $category = $categories->firstWhere('name', $default['label']);
            if (!$category) continue;

            $quickActions[] = [
                'id'             => null,
                'label'          => $default['label'],
                'default_amount' => $default['amount'],
                'direction'      => $default['direction'],
                'usage_count'    => 0,
                'category'       => $category,
            ];
        }

        return $quickActions;
    }
}