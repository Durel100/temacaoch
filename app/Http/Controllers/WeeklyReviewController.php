<?php

namespace App\Http\Controllers;

use App\Http\Services\WeeklyReviewService;
use Illuminate\Http\Request;

class WeeklyReviewController extends Controller
{
    /**
     * Bilan hebdomadaire du coach pour la page Statistiques.
     * Par défaut : la semaine qui vient de se terminer (cohérent avec « fin de semaine »).
     * ?current=1 pour forcer la semaine en cours.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'profile', 'incomeSources', 'fixedCharges',
            'transactions', 'financialGoals', 'tontineGroups.cycles.contribution',
        ]);

        $weekStart = $request->boolean('current')
            ? now()->startOfWeek()
            : now()->subWeek()->startOfWeek();

        $service = new WeeklyReviewService($user);
        $review  = $service->forWeek($weekStart);

        return response()->json([
            'review'     => $review,
            'week_start' => $weekStart->toDateString(),
        ]);
    }
}