<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    /**
     * Enregistrer un remboursement partiel ou total
     */
    public function repay(Request $request, Debt $debt)
    {
        // Vérifier que la dette appartient bien à l'utilisateur connecté
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $debt->remaining_amount,
        ]);

        $newRemaining = max(0, $debt->remaining_amount - $validated['amount']);

        $debt->update([
            'remaining_amount' => $newRemaining,
        ]);

        return back()->with('success', $newRemaining === 0.0
            ? "Dette \"{$debt->label}\" soldée ! 🎉"
            : "Remboursement de " . number_format($validated['amount']) . " FCFA enregistré."
        );
    }

    /**
     * Modifier une dette (renommer, ajuster les montants).
     * Fonctionne aussi pour la dette de découvert créée par le système —
     * on ne modifie jamais le flag is_system, seulement les champs éditables.
     */
    public function update(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

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

        return back()->with('success', "Dette mise à jour.");
    }
}