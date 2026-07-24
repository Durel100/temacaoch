<?php

namespace App\Http\Controllers;

use App\Models\TontineGroup;
use App\Models\TontineCycle;
use App\Models\TontineContribution;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Debt;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TontineController extends Controller
{
    /**
     * Liste de toutes les tontines de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tontines = $user->tontineGroups()
            ->with(['cycles' => function ($q) {
                $q->orderBy('cycle_number');
            }, 'cycles.contribution'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($tontine) {
                $nextCycle = $tontine->nextCycle();
                $myPayoutCycle = $tontine->myPayoutCycle();

                return [
                    'id' => $tontine->id,
                    'name' => $tontine->name,
                    'contribution_amount' => $tontine->contribution_amount,
                    'frequency' => $tontine->frequency,
                    'total_members' => $tontine->total_members,
                    'my_position' => $tontine->my_position,
                    'start_date' => $tontine->start_date,
                    'is_active' => $tontine->is_active,
                    'cycles' => $tontine->cycles,
                    'next_cycle' => $nextCycle,
                    'my_payout_cycle' => $myPayoutCycle,
                    'payout_amount' => $tontine->contribution_amount * $tontine->total_members,
                    'late_contributions_count' => $tontine->cycles()
                        ->whereHas('contribution', fn($q) => $q->where('status', 'late'))
                        ->count(),
                ];
            });

        return Inertia::render('Tontines/Index', [
            'tontines' => $tontines,
        ]);
    }

    /**
     * Formulaire de création
     */
    public function create()
    {
        return Inertia::render('Tontines/Create');
    }

    /**
     * Enregistrer une nouvelle tontine
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'contribution_amount' => 'required|numeric|min:500',
            'cycle_days'          => 'nullable|integer|min:1|max:365',
            'total_members'       => 'required|integer|min:2|max:50',
            'my_positions'        => 'required|array|min:1',
            'my_positions.*'      => 'required|integer|min:1',
            'start_date' => 'required|date',
            'cycle_months'   => 'nullable|integer|min:1|max:24',
            'frequency_type' => 'required|in:days,months',
        ]);

        // Vérifier que toutes les positions sont valides
        foreach ($validated['my_positions'] as $pos) {
            if ($pos > $validated['total_members']) {
                return back()->withErrors([
                    'my_positions' => "La position {$pos} dépasse le nombre de membres.",
                ]);
            }
        }

        // Vérifier doublons de positions
        if (count($validated['my_positions']) !== count(array_unique($validated['my_positions']))) {
            return back()->withErrors([
                'my_positions' => 'Tu as des positions en double.',
            ]);
        }

        $activeTontinesCount = $request->user()
            ->tontineGroups()
            ->where('is_active', true)
            ->count();

        if ($activeTontinesCount >= 3) {
            return back()->withErrors([
                'name' => 'Tu ne peux pas avoir plus de 3 tontines actives simultanément.',
            ]);
        }

        $tontine = $request->user()->tontineGroups()->create([
            'name'                => $validated['name'],
            'contribution_amount' => $validated['contribution_amount'],
            'cycle_days'          => $validated['cycle_days'],
            'total_members'       => $validated['total_members'],
            'my_position'         => $validated['my_positions'][0], // compatibilité
            'my_positions'        => $validated['my_positions'],
            'start_date'          => $validated['start_date'],
            'is_active'           => true,
            'cycle_months'   => $validated['cycle_months'],
            'frequency_type' => $validated['frequency_type'],
        ]);

        $tontine->generateCycles();

        // Marquer automatiquement les cycles passés lors d'une création antidatée
        $today = now()->startOfDay();
        $tontine->cycles()->each(function ($cycle) use ($today) {
            $cycleDate = \Carbon\Carbon::parse($cycle->scheduled_date)->startOfDay();

            if ($cycleDate->lt($today)) {
                if ($cycle->is_my_turn) {
                    // Mon tour de réception déjà passé → completed
                    $cycle->update(['status' => 'completed']);
                } else {
                    // Cotisation déjà due → créer contribution late
                    if (!$cycle->contribution) {
                        $cycle->contribution()->create([
                            'amount_due'  => $cycle->group->contribution_amount,
                            'amount_paid' => 0,
                            'status'      => 'late',
                        ]);
                    }
                }
            }
        });

        return redirect()->route('tontines.show', $tontine->id)
            ->with('success', 'Tontine créée.');
    }

    /**
     * Détail d'une tontine
     */
    public function show(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) {
            abort(403);
        }

        $tontine->load(['cycles' => function ($q) {
            $q->orderBy('cycle_number');
        }, 'cycles.contribution']);

        return Inertia::render('Tontines/Show', [
            'tontine' => $tontine,
            'payoutAmount' => $tontine->contribution_amount * $tontine->total_members,
            'myPayoutCycle' => $tontine->myPayoutCycle(),
            'nextCycle' => $tontine->nextCycle(),
        ]);
    }

    /**
     * Marquer une cotisation comme payée
     */
    public function markPaid(Request $request, TontineCycle $cycle)
    {
        if ($cycle->group->user_id !== $request->user()->id) {
            abort(403);
        }

        $user   = $request->user();
        $amount = $cycle->group->contribution_amount;

        $contribution = $cycle->contribution;

        if (!$contribution) {
            $contribution = $cycle->contribution()->create([
                'amount_due'  => $amount,
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);
        }

        $contribution->markAsPaid();

        // Créer une transaction "out" pour débiter le budget
        $category = Category::firstOrCreate(
            ['name' => 'Tontine', 'user_id' => null],
            [
                'icon'              => 'users',
                'default_direction' => 'out',
                'is_system'         => true,
                'translation_key'   => 'cat_tontine',
            ]
        );

        Transaction::create([
            'user_id'       => $user->id,
            'amount'        => $amount,
            'direction'     => 'out',
            'category_id'   => $category->id,
            'transacted_at' => now(),
            'source'        => 'manual_custom',
            'note'          => 'Cotisation tontine : ' . $cycle->group->name,
        ]);

        // Synchroniser le découvert
        $this->syncOverdraftDebt($user);

        // Préparer le prochain cycle
        $nextCycle = $cycle->group->cycles()
            ->where('cycle_number', $cycle->cycle_number + 1)
            ->first();

        if ($nextCycle && !$nextCycle->contribution) {
            $nextCycle->contribution()->create([
                'amount_due'  => $amount,
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);
        }

        return back()->with('success', 'Cotisation enregistrée — déduite de ton budget.');
    }

    /**
     * Synchronise la dette de découvert après une dépense
     */
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
            ->whereYear('created_at',  now()->year)
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
            ]);
        }
    }

    /**
     * Marquer la réception de la tontine (quand c'est mon tour)
     */
    public function markReceived(Request $request, TontineCycle $cycle)
    {
        if ($cycle->group->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$cycle->is_my_turn) {
            abort(403, 'Ce n\'est pas ton tour de réception.');
        }

        $cycle->update(['status' => 'completed']);

        return back()->with('success', 'Réception enregistrée. Félicitations ! 🎉');
    }

    /**
     * Désactiver une tontine (pas de suppression — on garde l'historique)
     */
    public function deactivate(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) {
            abort(403);
        }

        $tontine->update(['is_active' => false]);

        return redirect()->route('tontines.index')
            ->with('success', 'Tontine désactivée.');
    }
}