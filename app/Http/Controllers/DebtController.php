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
}