<?php

namespace App\Http\Controllers;

use App\Models\FinancialGoal;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class FinancialGoalController extends Controller
{
    /**
     * Créer un objectif depuis le dashboard
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:1000',
            'target_date' => 'nullable|date|after:today',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['current_amount'] = 0;

        FinancialGoal::create($validated);

        return back()->with('success', 'Objectif créé.');
    }

    /**
     * Mettre à jour la progression d'un objectif
     */
    public function addProgress(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $goal->update([
            'current_amount' => min(
                $goal->target_amount,
                $goal->current_amount + $validated['amount']
            ),
        ]);

        return back()->with('success', 'Progression mise à jour.');
    }

    /**
     * Supprimer un objectif
     */
    public function destroy(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);
        $goal->delete();
        return back()->with('success', 'Objectif supprimé.');
    }

    /**
     * Estimation IA pour atteindre un objectif
     */
    public function estimate(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        $user = $request->user()->load([
            'profile', 'incomeSources', 'fixedCharges',
            'debts', 'tontineGroups', 'transactions',
        ]);

        $calculator = new FinancialCalculatorService($user);

        // Vérifier si on a assez de données
        $hasHistory = $user->transactions()
            ->where('created_at', '>=', now()->subMonths(2))
            ->count() >= 5;

        $resteAVivre = $calculator->getResteAVivre();
        $variableSpending = $calculator->getCurrentMonthVariableSpending();
        $realRemaining = $calculator->getRealRemainingBudget();
        $remaining = $goal->target_amount - $goal->current_amount;

        if (!$hasHistory) {
            return response()->json([
                'estimation' => null,
                'message' => "Nous n'avons pas encore assez de données sur vous pour faire une approximation. Continuez à enregistrer vos transactions pendant quelques semaines.",
            ]);
        }

        // Contexte pour l'IA
        $context = [
            'objectif' => [
                'label' => $goal->label,
                'montant_cible' => $goal->target_amount,
                'montant_actuel' => $goal->current_amount,
                'restant' => $remaining,
                'date_cible' => $goal->target_date,
            ],
            'situation_financiere' => [
                'reste_a_vivre_mensuel' => $resteAVivre,
                'depenses_variables_moyennes' => $variableSpending,
                'budget_reel_restant' => $realRemaining,
                'revenu_reference' => $calculator->getSafeIncomeBaseline(),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['verify' => config('services.anthropic.verify_ssl', true)])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 300,
                'system' => "Tu es TemaCoach, un coach financier. Analyse la situation et donne une estimation réaliste pour atteindre l'objectif. Réponds UNIQUEMENT en JSON avec ce format exact :
{
  \"montant_epargne_suggere\": number,
  \"duree_mois\": number,
  \"date_estimee\": \"string (mois année)\",
  \"commentaire\": \"string (1 phrase max, direct et factuel)\"
}
N'invente jamais de chiffres — base-toi uniquement sur les données fournies.",
                'messages' => [[
                    'role' => 'user',
                    'content' => 'Voici ma situation : ' . json_encode($context, JSON_UNESCAPED_UNICODE),
                ]],
            ]);

            if ($response->failed()) {
                return response()->json(['estimation' => null, 'message' => 'Service temporairement indisponible.']);
            }

            $raw = $response->json('content.0.text');
            $clean = preg_replace('/```json|```/', '', $raw);
            $estimation = json_decode(trim($clean), true);

            return response()->json([
                'estimation' => $estimation,
                'message' => null,
            ]);

        } catch (\Exception $e) {
            return response()->json(['estimation' => null, 'message' => 'Service temporairement indisponible.']);
        }
    }

    public function index(Request $request)
    {
        $goals = $request->user()->financialGoals()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => [
                'id'               => $g->id,
                'label'            => $g->label,
                'target_amount'    => $g->target_amount,
                'current_amount'   => $g->current_amount,
                'target_date'      => $g->target_date,
                'progress_percent' => $g->progress_percent,
            ]);

        return Inertia::render('Goals/Index', ['goals' => $goals]);
    }
}