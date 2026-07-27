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
                $nextCycle     = $tontine->nextCycle();
                $myPayoutCycle = $tontine->myPayoutCycle();

                return [
                    'id'                       => $tontine->id,
                    'name'                     => $tontine->name,
                    'contribution_amount'      => $tontine->contribution_amount,
                    'frequency'                => $tontine->frequency,
                    'total_members'            => $tontine->total_members,
                    'my_position'              => $tontine->my_position,
                    'start_date'               => $tontine->start_date,
                    'is_active'                => $tontine->is_active,
                    'cycles'                   => $tontine->cycles,
                    'next_cycle'               => $nextCycle,
                    'my_payout_cycle'          => $myPayoutCycle,
                    'payout_amount'            => $tontine->contribution_amount * $tontine->total_members,
                    'late_contributions_count' => $tontine->cycles()
                        ->whereHas('contribution', fn ($q) => $q->where('status', 'late'))
                        ->count(),
                ];
            });

        return Inertia::render('Tontines/Index', [
            'tontines' => $tontines,
        ]);
    }

    public function create()
    {
        return Inertia::render('Tontines/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'contribution_amount' => 'required|numeric|min:500',
            'cycle_days'          => 'nullable|integer|min:1|max:365',
            'total_members'       => 'required|integer|min:2|max:50',
            'my_positions'        => 'required|array|min:1',
            'my_positions.*'      => 'required|integer|min:1',
            'start_date'          => 'required|date',
            'prepaid_cycles'      => 'nullable|array',
            'prepaid_cycles.*'    => 'integer|min:1',
            'cycle_months'        => 'nullable|integer|min:1|max:24',
            'frequency_type'      => 'required|in:days,months',
        ]);

        foreach ($validated['my_positions'] as $pos) {
            if ($pos > $validated['total_members']) {
                return back()->withErrors(['my_positions' => "La position {$pos} dépasse le nombre de membres."]);
            }
        }

        if (count($validated['my_positions']) !== count(array_unique($validated['my_positions']))) {
            return back()->withErrors(['my_positions' => 'Tu as des positions en double.']);
        }

        $activeTontinesCount = $request->user()->tontineGroups()->where('is_active', true)->count();
        if ($activeTontinesCount >= 3) {
            return back()->withErrors(['name' => 'Tu ne peux pas avoir plus de 3 tontines actives simultanément.']);
        }

        $tontine = $request->user()->tontineGroups()->create([
            'name'                => $validated['name'],
            'contribution_amount' => $validated['contribution_amount'],
            'cycle_days'          => $validated['frequency_type'] === 'days' ? $validated['cycle_days'] : null,
            'cycle_months'        => $validated['frequency_type'] === 'months' ? $validated['cycle_months'] : null,
            'frequency_type'      => $validated['frequency_type'],
            'total_members'       => $validated['total_members'],
            'my_position'         => $validated['my_positions'][0],
            'my_positions'        => $validated['my_positions'],
            'start_date'          => $validated['start_date'],
            'is_active'           => true,
        ]);

        $tontine->generateCycles();

        // ── Marquer automatiquement les cycles passés ─────────────────
        $today          = now()->startOfDay();
        $prepaidCycles  = $validated['prepaid_cycles'] ?? [];

        $tontine->cycles()->each(function ($cycle) use ($today, $prepaidCycles) {
            $cycleDate = \Carbon\Carbon::parse($cycle->scheduled_date)->startOfDay();

            if ($cycleDate->lt($today)) {
                // Cycles passés
                if ($cycle->is_my_turn) {
                    // Mon tour de réception passé → completed
                    $cycle->update(['status' => 'completed']);
                } else {
                    // Cotisation passée → marquée payée sans transaction
                    if (!$cycle->contribution) {
                        $cycle->contribution()->create([
                            'amount_due'  => $cycle->group->contribution_amount,
                            'amount_paid' => $cycle->group->contribution_amount,
                            'status'      => 'paid',
                            'paid_date'   => $cycle->scheduled_date,
                        ]);
                    }
                }
            } elseif (in_array($cycle->cycle_number, $prepaidCycles)) {
                // Cycles futurs prépayés déclarés par l'utilisateur
                // → marqués payés SANS créer de transaction (l'argent est déjà sorti)
                if (!$cycle->contribution) {
                    $cycle->contribution()->create([
                        'amount_due'  => $cycle->group->contribution_amount,
                        'amount_paid' => $cycle->group->contribution_amount,
                        'status'      => 'paid',
                        'paid_date'   => now()->toDateString(),
                    ]);
                }
            }
        });

        return redirect()->route('tontines.show', $tontine->id)
            ->with('success', 'Tontine créée. Les cycles passés et prépayés ont été marqués automatiquement.');
    }

    public function show(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) abort(403);

        $tontine->load(['cycles' => function ($q) {
            $q->orderBy('cycle_number');
        }, 'cycles.contribution']);

        return Inertia::render('Tontines/Show', [
            'tontine'       => $tontine,
            'payoutAmount'  => $tontine->contribution_amount * $tontine->total_members,
            'myPayoutCycle' => $tontine->myPayoutCycle(),
            'nextCycle'     => $tontine->nextCycle(),
        ]);
    }

    public function markPaid(Request $request, TontineCycle $cycle)
    {
        if ($cycle->group->user_id !== $request->user()->id) abort(403);

        $user  = $request->user();
        $group = $cycle->group;

        // Montant = cotisation × nombre de mes positions (si plusieurs noms)
        // Parser my_positions si c'est une string JSON (cas rare selon DB)
        $myPositionsRaw   = $group->my_positions;
        $myPositionsArr   = is_array($myPositionsRaw)
            ? $myPositionsRaw
            : (is_string($myPositionsRaw) ? json_decode($myPositionsRaw, true) : null);
        $myPositionsCount = !empty($myPositionsArr) ? count($myPositionsArr) : 1;
        $amountPerCycle   = $group->contribution_amount;
        $totalAmount      = $amountPerCycle * $myPositionsCount;

        $contribution = $cycle->contribution;

        if (!$contribution) {
            $contribution = $cycle->contribution()->create([
                'amount_due'  => $totalAmount,
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);
        }

        $contribution->markAsPaid();

        $category = Category::firstOrCreate(
            ['name' => 'Tontine', 'user_id' => null],
            [
                'icon'              => 'users',
                'default_direction' => 'out',
                'is_system'         => true,
                'translation_key'   => 'cat_tontine',
            ]
        );

        $posNote = $myPositionsCount > 1
            ? " ({$myPositionsCount} noms × " . number_format($amountPerCycle, 0, ',', ' ') . " FCFA)"
            : "";

        Transaction::create([
            'user_id'       => $user->id,
            'amount'        => $totalAmount,
            'direction'     => 'out',
            'category_id'   => $category->id,
            'transacted_at' => now(),
            'source'        => 'manual_custom',
            'note'          => 'Cotisation tontine : ' . $group->name . $posNote,
        ]);

        $this->syncOverdraftDebt($user);

        $nextCycle = $group->cycles()
            ->where('cycle_number', $cycle->cycle_number + 1)
            ->first();

        if ($nextCycle && !$nextCycle->contribution) {
            $nextCycle->contribution()->create([
                'amount_due'  => $totalAmount,
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);
        }

        return back()->with('success', 'Cotisation enregistrée — déduite de ton budget.');
    }

    public function markReceived(Request $request, TontineCycle $cycle)
    {
        if ($cycle->group->user_id !== $request->user()->id) abort(403);
        if (!$cycle->is_my_turn) abort(403, "Ce n'est pas ton tour de réception.");

        $cycle->update(['status' => 'completed']);

        return back()->with('success', 'Réception enregistrée. Félicitations ! 🎉');
    }

    public function deactivate(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) abort(403);

        $tontine->update(['is_active' => false]);

        return redirect()->route('tontines.index')
            ->with('success', 'Tontine désactivée.');
    }

    // ── NOUVEAU : Réactiver une tontine ──────────────────────────────
    public function reactivate(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) abort(403);

        $activeTontinesCount = $request->user()
            ->tontineGroups()
            ->where('is_active', true)
            ->count();

        if ($activeTontinesCount >= 3) {
            return back()->withErrors(['error' => 'Tu ne peux pas avoir plus de 3 tontines actives simultanément.']);
        }

        $tontine->update(['is_active' => true]);

        return back()->with('success', 'Tontine réactivée.');
    }

    // ── NOUVEAU : Supprimer une tontine ──────────────────────────────
    public function destroy(Request $request, TontineGroup $tontine)
    {
        if ($tontine->user_id !== $request->user()->id) abort(403);

        // Supprimer les contributions d'abord
        $cycleIds = $tontine->cycles()->pluck('id');
        TontineContribution::whereIn('tontine_cycle_id', $cycleIds)->delete();

        // Supprimer les cycles
        $tontine->cycles()->delete();

        // Supprimer la tontine
        $tontine->delete();

        return redirect()->route('tontines.index')
            ->with('success', 'Tontine supprimée.');
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
}