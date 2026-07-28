<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\QuickAction;
use App\Models\Transaction;
use App\Models\Debt;
use App\Models\FinancialGoal;
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
        // Récupérer les QuickActions personnalisées triées par usage
        $quickActions = $user->quickActions()
            ->with('category')
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->limit(8) // max 8 boutons rapides
            ->get();

        // Fallback sur les actions par défaut si aucune action personnalisée
        if ($quickActions->isEmpty()) {
            $quickActions = collect($this->getDefaultQuickActions($user->id, $categories));
        }

        return Inertia::render('Transactions/Create', [
            'categories'   => $categories,
            'quickActions' => $quickActions,
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
            'source'          => 'nullable|in:quick_action,manual_custom',
            'transacted_at'   => 'required|date',
            'note'            => 'nullable|string|max:500',
        ]);

        // Parser la date avec la timezone Douala pour éviter le décalage -1h
        $validated['transacted_at'] = \Carbon\Carbon::parse(
            $validated['transacted_at'],
            'Africa/Douala'
        )->setTimezone(config('app.timezone', 'Africa/Douala'));
        $validated['source']  = $validated['source'] ?? ($validated['quick_action_id'] ? 'quick_action' : 'manual_custom');
        $validated['user_id'] = $request->user()->id;

        // Si pas de catégorie → créer une catégorie avec le nom de la note
        // ou bloquer si ni catégorie ni note
        if (empty($validated['category_id'])) {
            $note = trim($validated['note'] ?? '');

            if (!empty($note)) {
                // Créer une catégorie personnalisée avec le nom de la note
                $category = Category::firstOrCreate(
                    [
                        'name'    => $note,
                        'user_id' => $validated['user_id'],
                    ],
                    [
                        'icon'              => 'wallet',
                        'default_direction' => $validated['direction'] ?? 'out',
                        'is_system'         => false,
                    ]
                );
                $validated['category_id'] = $category->id;
            } else {
                return back()->withErrors([
                    'category_id' => 'Sélectionne un type ou entre une note pour identifier cette transaction.',
                ]);
            }
        }

        Transaction::create($validated);

        // Incrémenter l'usage si c'est une action rapide existante
        if (!empty($validated['quick_action_id'])) {
            QuickAction::find($validated['quick_action_id'])?->incrementUsage();
        }

        // Créer ou mettre à jour la QuickAction pour les transactions manuelles
        if (($validated['source'] ?? '') === 'manual_custom') {
            $category = !empty($validated['category_id'])
                ? Category::find($validated['category_id'])
                : null;

            // Label = catégorie OU note OU on ignore
            $label = $category?->name
                ?? (!empty($validated['note']) ? $validated['note'] : null);

            // Créer la QuickAction uniquement si on a un label significatif
            if ($label) {
                $quickAction = QuickAction::firstOrCreate(
                    [
                        'user_id'   => $validated['user_id'],
                        'direction' => $validated['direction'],
                        'label'     => $label,
                    ],
                    [
                        'category_id'    => $validated['category_id'] ?? null,
                        'default_amount' => $validated['amount'],
                        'usage_count'    => 0,
                    ]
                );

                // Moyenne glissante pondérée pour le montant
                $newAmount = round(
                    ($quickAction->default_amount * $quickAction->usage_count + $validated['amount'])
                    / ($quickAction->usage_count + 1)
                );
                $quickAction->update(['default_amount' => $newAmount]);
                $quickAction->incrementUsage();
            }
        }

        // Mettre à jour la progression de l'objectif si catégorie liée
        $this->syncGoalProgress($request->user(), $validated['category_id'], $validated['amount'], $validated['direction']);

        $this->syncOverdraftDebt($request->user());

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

        $categoryId = $transaction->category_id;
        $amount     = $transaction->amount;
        $direction  = $transaction->direction;
        $transaction->delete();

        // Recalculer la progression de l'objectif si catégorie liée
        // La suppression inverse la direction
        $inverseDirection = $direction === 'out' ? 'in' : 'out';
        $this->syncGoalProgress($request->user(), $categoryId, $amount, $inverseDirection);

        $this->syncOverdraftDebt($request->user());

        return back()->with('success', 'Transaction supprimée.');
    }

    /**
     * Met à jour la progression d'un objectif selon la transaction
     */
    private function syncGoalProgress($user, ?int $categoryId, float $amount, string $direction): void
    {
        if (!$categoryId) return;

        // Chercher si cette catégorie est liée à un objectif actif
        $goal = FinancialGoal::where('user_id', $user->id)
            ->where('category_id', $categoryId)
            ->where('is_archived', false)
            ->first();

        if (!$goal) return;

        if ($direction === 'out') {
            // Transaction "out" = épargne vers l'objectif → augmente current_amount
            $newAmount = min($goal->target_amount, $goal->current_amount + $amount);
        } else {
            // Transaction "in" = retrait de l'objectif → diminue current_amount
            $newAmount = max(0, $goal->current_amount - $amount);
        }

        $goal->update(['current_amount' => $newAmount]);

        // Archivage automatique si 100% atteint
        $goal->refresh();
        $goal->checkAndAutoArchive();
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