<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FinancialGoal;
use App\Http\Services\FinancialCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class FinancialGoalController extends Controller
{
    /**
     * Page dédiée aux objectifs
     */
    public function index(Request $request)
    {
        $goals = $request->user()->financialGoals()
            ->where('is_archived', false)
            ->with('category')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => $this->mapGoal($g));

        $archivedGoals = $request->user()->financialGoals()
            ->where('is_archived', true)
            ->orderByDesc('archived_at')
            ->get()
            ->map(fn ($g) => $this->mapGoal($g));

        return Inertia::render('Goals/Index', [
            'goals'         => $goals,
            'archivedGoals' => $archivedGoals,
        ]);
    }

    private function mapGoal(FinancialGoal $g): array
    {
        return [
            'id'                  => $g->id,
            'label'               => $g->label,
            'target_amount'       => $g->target_amount,
            'current_amount'      => $g->current_amount,
            'target_date'         => $g->target_date,
            'progress_percent'    => $g->progress_percent,
            'is_archived'         => $g->is_archived,
            'archived_at'         => $g->archived_at,
            'category_id'         => $g->category_id,
            'category_name'       => $g->category?->name,
            'can_estimate'        => $g->canEstimate(),
            'last_estimated_at'   => $g->last_estimated_at,
            'estimation_locked_until' => $g->last_estimated_at
                ? $g->last_estimated_at->copy()->addMonths(2)->toDateString()
                : null,
        ];
    }

    /**
     * Créer un objectif et sa catégorie associée
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:1000',
            'target_date'   => 'nullable|date|after:today',
        ]);

        $user = $request->user();

        // Créer la catégorie liée à cet objectif
        $category = Category::create([
            'name'              => $validated['label'],
            'user_id'           => $user->id,
            'icon'              => 'target',
            'default_direction' => 'out', // épargner = sortie de budget
            'is_system'         => false,
            'translation_key'   => null,
        ]);

        $goal = FinancialGoal::create([
            'user_id'        => $user->id,
            'label'          => $validated['label'],
            'target_amount'  => $validated['target_amount'],
            'current_amount' => 0,
            'target_date'    => $validated['target_date'] ?? null,
            'category_id'    => $category->id,
            'is_archived'    => false,
        ]);

        // Lier la catégorie à l'objectif
        $category->update(['goal_id' => $goal->id]);

        return back()->with('success', 'Objectif créé. Tu peux maintenant enregistrer des transactions avec la catégorie "' . $validated['label'] . '" pour alimenter cet objectif.');
    }

    /**
     * Archiver manuellement un objectif (objectif atteint)
     */
    public function archive(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        $goal->update([
            'is_archived'    => true,
            'archived_at'    => now(),
            'current_amount' => $goal->target_amount, // marquer comme 100%
        ]);

        return back()->with('success', '🎉 Félicitations ! Objectif "' . $goal->label . '" atteint et archivé.');
    }

    /**
     * Supprimer un objectif et sa catégorie associée
     */
    public function destroy(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        // Supprimer la catégorie liée si elle existe
        if ($goal->category_id) {
            Category::where('id', $goal->category_id)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        $goal->delete();

        return back()->with('success', 'Objectif supprimé.');
    }

    /**
     * Estimation IA — verrouillée 1 mois, réestimable après 2 mois
     */
    public function estimate(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        // Vérifier si l'estimation est verrouillée
        if (!$goal->canEstimate()) {
            $unlockDate = $goal->last_estimated_at->copy()->addMonths(2)->translatedFormat('d M Y');
            return response()->json([
                'estimation' => null,
                'locked'     => true,
                'message'    => "Estimation disponible à nouveau le {$unlockDate}.",
            ]);
        }

        $user = $request->user()->load([
            'profile', 'incomeSources', 'fixedCharges',
            'debts', 'tontineGroups', 'transactions',
        ]);

        $calculator = new FinancialCalculatorService($user);

        // Vérifier si on a assez de données (au moins 1 mois)
        $hasHistory = $user->transactions()
            ->where('created_at', '>=', now()->subMonth())
            ->count() >= 5;

        if (!$hasHistory) {
            return response()->json([
                'estimation' => null,
                'locked'     => false,
                'message'    => "Il faut au moins 1 mois de transactions pour faire une estimation fiable. Continue à enregistrer tes transactions !",
            ]);
        }

        $locale    = $user->locale ?? 'fr';
        $remaining = $goal->target_amount - $goal->current_amount;

        $context = [
            'objectif' => [
                'label'          => $goal->label,
                'montant_cible'  => $goal->target_amount,
                'montant_actuel' => $goal->current_amount,
                'restant'        => $remaining,
                'date_cible'     => $goal->target_date,
            ],
            'situation_financiere' => [
                'reste_a_vivre_mensuel'      => $calculator->getResteAVivre(),
                'depenses_variables_moyennes' => $calculator->getCurrentMonthVariableSpending(),
                'budget_reel_restant'         => $calculator->getRealRemainingBudget(),
                'revenu_reference'            => $calculator->getSafeIncomeBaseline(),
            ],
        ];

        $systemPrompt = $locale === 'en'
            ? "You are TemaCoach. Analyze the situation and give a realistic estimate to reach the goal. Respond ONLY in JSON with this exact format:
{\"montant_epargne_suggere\": number, \"duree_mois\": number, \"date_estimee\": \"string (month year)\", \"commentaire\": \"string (1 sentence max, direct and factual)\"}
Never invent numbers — use only the data provided."
            : "Tu es TemaCoach. Analyse la situation et donne une estimation réaliste pour atteindre l'objectif. Réponds UNIQUEMENT en JSON avec ce format exact :
{\"montant_epargne_suggere\": number, \"duree_mois\": number, \"date_estimee\": \"string (mois année)\", \"commentaire\": \"string (1 phrase max, direct et factuel)\"}
N'invente jamais de chiffres — base-toi uniquement sur les données fournies.";

        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['verify' => config('services.anthropic.verify_ssl', true)])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 300,
                'system'     => $systemPrompt,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => json_encode($context, JSON_UNESCAPED_UNICODE),
                ]],
            ]);

            if ($response->failed()) {
                return response()->json(['estimation' => null, 'locked' => false, 'message' => 'Service temporairement indisponible.']);
            }

            $raw       = $response->json('content.0.text');
            $clean     = preg_replace('/```json|```/', '', $raw);
            $estimation = json_decode(trim($clean), true);

            // Verrouiller l'estimation pour 2 mois
            $goal->update(['last_estimated_at' => now()]);

            return response()->json([
                'estimation' => $estimation,
                'locked'     => false,
                'message'    => null,
            ]);

        } catch (\Exception $e) {
            return response()->json(['estimation' => null, 'locked' => false, 'message' => 'Service temporairement indisponible.']);
        }
    }
}