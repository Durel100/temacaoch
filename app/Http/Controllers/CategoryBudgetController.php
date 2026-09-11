<?php

namespace App\Http\Controllers;

use App\Models\CategoryBudget;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryBudgetController extends Controller
{
    /**
     * Liste des catégories de dépense de l'utilisateur avec, pour le cycle en cours :
     * le budget défini (le cas échéant), le montant dépensé, le % et l'état de dépassement.
     * Sert à la fois aux barres et au gestionnaire de budgets sur le dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user()->load(['profile', 'incomeSources', 'fixedCharges', 'transactions']);

        $calculator = new FinancialCalculatorService($user);
        [$start, $end] = $calculator->getFinancialCycleRange();

        // Dépenses par catégorie sur le cycle
        $spentByCat = $user->transactions()
            ->where('direction', 'out')
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<', $end)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        // Budgets de l'utilisateur (table pivot)
        $budgets = CategoryBudget::where('user_id', $user->id)
            ->pluck('monthly_budget', 'category_id');

        // Catégories de dépense disponibles : perso (user_id) + système partagées
        $categories = DB::table('categories')
            ->where('default_direction', 'out')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('is_system', true);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $list = $categories->map(function ($c) use ($spentByCat, $budgets) {
            $budget = (float) ($budgets[$c->id] ?? 0);
            $spent  = (float) ($spentByCat[$c->id] ?? 0);

            return [
                'id'        => $c->id,
                'name'      => $c->name,
                'budget'    => $budget,
                'spent'     => $spent,
                'percent'   => $budget > 0 ? min(100, round(($spent / $budget) * 100)) : 0,
                'is_over'   => $budget > 0 && $spent > $budget,
                'remaining' => $budget > 0 ? max(0, $budget - $spent) : 0,
            ];
        })->values();

        return response()->json(['categories' => $list]);
    }

    /**
     * Définit ou retire le budget mensuel d'une catégorie (montant vide ou 0 → retiré).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'monthly_budget' => 'nullable|numeric|min:0',
        ]);

        $user   = $request->user();
        $budget = $validated['monthly_budget'] ?? null;

        if (!$budget || $budget <= 0) {
            CategoryBudget::where('user_id', $user->id)
                ->where('category_id', $validated['category_id'])
                ->delete();

            return back()->with('success', 'Budget retiré.');
        }

        CategoryBudget::updateOrCreate(
            ['user_id' => $user->id, 'category_id' => $validated['category_id']],
            ['monthly_budget' => $budget]
        );

        return back()->with('success', 'Budget enregistré.');
    }
}