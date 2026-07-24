<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\QuickAction;
use App\Models\Transaction;
use App\Models\Debt;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Page d'ajout avec boutons rapides
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $categories = Category::availableFor($user->id);
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

    /**
     * Enregistrer une transaction
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'          => 'required|numeric|min:1',
            'direction'       => 'required|in:in,out',
            'category_id'     => 'nullable|exists:categories,id',
            'quick_action_id' => 'nullable|exists:quick_actions,id',
            'fixed_charge_id' => 'nullable|exists:fixed_charges,id',
            'source'          => 'nullable|in:quick_action,manual_custom',
            'transacted_at'   => 'required|date',
            'note'            => 'nullable|string|max:500',
        ]);

        $validated['transacted_at'] = $validated['transacted_at'] ?? now();
        $validated['source']  = $validated['source'] ?? ($validated['quick_action_id'] ? 'quick_action' : 'manual_custom');
        $validated['user_id'] = $request->user()->id;

        // Si pas de catégorie → catégorie "Autre" par défaut
        if (empty($validated['category_id'])) {
            $direction = $validated['direction'] ?? 'out';
            $default = Category::firstOrCreate(
                ['name' => 'Autre', 'user_id' => null],
                [
                    'icon'              => 'wallet',
                    'default_direction' => $direction,
                    'is_system'         => true,
                    'translation_key'   => 'cat_other',
                ]
            );
            $validated['category_id'] = $default->id;
        }

        // Lier au fixed_charge_id via le bouton rapide si non fourni
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

        // ← AJOUT : vérifier le budget après chaque dépense
        if ($validated['direction'] === 'out') {
            $this->syncOverdraftDebt($request->user());
        }

        // ← AJOUT : supprimer le découvert si une entrée remet le budget positif
        if ($validated['direction'] === 'in') {
            $this->syncOverdraftDebt($request->user());
        }

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction enregistrée.');
    }

    /**
     * Synchronise la dette de découvert selon le budget réel
     * - Budget négatif → crée ou met à jour la dette "Découvert budget"
     * - Budget positif → supprime la dette de découvert si elle existe
     */
    private function syncOverdraftDebt($user): void
    {
        // Recharger toutes les relations nécessaires au calcul
        $user->load([
            'profile',
            'dependents',
            'incomeSources',
            'fixedCharges',
            'debts',
            'financialGoals',
            'tontineGroups',
        ]);

        $calculator    = new FinancialCalculatorService($user);
        $realRemaining = $calculator->getRealRemainingBudget();

        // Récupérer la dette de découvert du mois en cours si elle existe
        $existingDebt = Debt::where('user_id', $user->id)
            ->where('label', 'Découvert budget')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->first();

        if ($realRemaining >= 0) {
            // Budget positif → supprimer le découvert s'il existait
            $existingDebt?->delete();
            return;
        }

        // Budget négatif → montant du découvert
        $overdraftAmount = round(abs($realRemaining), 2);

        if ($existingDebt) {
            // Mettre à jour le montant
            $existingDebt->update([
                'remaining_amount' => $overdraftAmount,
                'total_amount'     => max($existingDebt->total_amount, $overdraftAmount),
            ]);
        } else {
            // Créer la dette avec user_id explicite
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

    /**
     * Historique des transactions
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = $user->transactions()->with('category:id,name,translation_key,icon,default_direction');

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $period = $request->get('period', 'month');

        match ($period) {
            'today' => $query->whereDate('transacted_at', today()),
            'week'  => $query->whereBetween('transacted_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'year'  => $query->whereYear('transacted_at', now()->year),
            default => $query->whereMonth('transacted_at', now()->month)
                             ->whereYear('transacted_at', now()->year),
        };

        $transactions = $query->orderByDesc('transacted_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $totals = $user->transactions()
            ->when($period === 'today', fn ($q) => $q->whereDate('transacted_at', today()))
            ->when($period === 'week',  fn ($q) => $q->whereBetween('transacted_at', [now()->startOfWeek(), now()->endOfWeek()]))
            ->when($period === 'year',  fn ($q) => $q->whereYear('transacted_at', now()->year))
            ->when(!in_array($period, ['today', 'week', 'year']), fn ($q) =>
                $q->whereMonth('transacted_at', now()->month)
                  ->whereYear('transacted_at', now()->year)
            )
            ->selectRaw('
                SUM(CASE WHEN direction = "in"  THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN direction = "out" THEN amount ELSE 0 END) as total_out,
                COUNT(*) as total_count
            ')
            ->first();

        $categories = Category::availableFor($user->id);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'totals'       => $totals,
            'categories'   => $categories,
            'filters'      => $request->only(['direction', 'category_id', 'period']),
        ]);
    }

    /**
     * Supprimer une transaction
     */
    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $wasOut = $transaction->direction === 'out';
        $transaction->delete();

        // Recalculer le découvert après suppression
        $this->syncOverdraftDebt($request->user());

        return back()->with('success', 'Transaction supprimée.');
    }

    /**
     * Boutons rapides par défaut
     */
    private function getDefaultQuickActions(int $userId, $categories): array
    {
        $defaults = [
            ['label' => 'Transport',              'amount' => 500,   'direction' => 'out'],
            ['label' => 'Nourriture/Marché',      'amount' => 2000,  'direction' => 'out'],
            ['label' => 'Recharge téléphonique',  'amount' => 1000,  'direction' => 'out'],
            ['label' => 'Retrait agent',           'amount' => 20000, 'direction' => 'out'],
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