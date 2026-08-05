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
            'id'                      => $g->id,
            'label'                   => $g->label,
            'target_amount'           => $g->target_amount,
            'current_amount'          => $g->current_amount,
            'target_date'             => $g->target_date,
            'progress_percent'        => $g->progress_percent,
            'is_archived'             => $g->is_archived,
            'archived_at'             => $g->archived_at,
            'category_id'             => $g->category_id,
            'category_name'           => $g->category?->name,
            'can_estimate'            => $g->canEstimate(),
            'last_estimated_at'       => $g->last_estimated_at,
            'estimation_locked_until' => $g->last_estimated_at
                ? $g->last_estimated_at->copy()->addMonths(2)->toDateString()
                : null,
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:1000',
            'target_date'   => 'nullable|date|after:today',
        ]);

        $user = $request->user();

        $category = Category::create([
            'name'              => $validated['label'],
            'user_id'           => $user->id,
            'icon'              => 'target',
            'default_direction' => 'out',
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

        $category->update(['goal_id' => $goal->id]);

        return back()->with('success', 'Objectif créé. Fais une transaction "Sortie" avec la catégorie "' . $validated['label'] . '" pour alimenter cet objectif.');
    }

    public function archive(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        $goal->update([
            'is_archived'    => true,
            'archived_at'    => now(),
            'current_amount' => $goal->target_amount,
        ]);

        return back()->with('success', '🎉 Félicitations ! Objectif "' . $goal->label . '" atteint et archivé.');
    }

    public function destroy(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

        if ($goal->category_id) {
            Category::where('id', $goal->category_id)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        $goal->delete();

        return back()->with('success', 'Objectif supprimé.');
    }

    public function estimate(Request $request, FinancialGoal $goal)
    {
        if ($goal->user_id !== $request->user()->id) abort(403);

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
        $locale     = $user->locale ?? 'fr';
        $remaining  = $goal->target_amount - $goal->current_amount;

        // Vérifier si on a assez de données (au moins 1 mois)
        $hasHistory = $user->transactions()
            ->where('created_at', '>=', now()->subMonth())
            ->count() >= 5;

        if (!$hasHistory) {
            $msg = $locale === 'en'
                ? "Not enough data yet. Keep recording your transactions for a few weeks!"
                : "Il faut au moins 1 mois de transactions pour une estimation fiable. Continue à enregistrer tes transactions !";
            return response()->json(['estimation' => null, 'locked' => false, 'message' => $msg]);
        }

        // ── Calcul de la granularité selon la date cible ──────────────
        $targetDate   = $goal->target_date;
        $daysLeft     = $targetDate ? now()->diffInDays($targetDate, false) : null;
        $weeksLeft    = $daysLeft !== null ? floor($daysLeft / 7) : null;
        $monthsLeft   = $daysLeft !== null ? ceil($daysLeft / 30) : null;

        // Granularité : jour / semaine / mois
        if ($daysLeft !== null && $daysLeft <= 30) {
            $granularity    = 'day';
            $granularityFr  = 'jour';
            $granularityEn  = 'day';
            $periodsLeft    = max(1, $daysLeft);
        } elseif ($daysLeft !== null && $daysLeft <= 90) {
            $granularity    = 'week';
            $granularityFr  = 'semaine';
            $granularityEn  = 'week';
            $periodsLeft    = max(1, $weeksLeft);
        } else {
            $granularity    = 'month';
            $granularityFr  = 'mois';
            $granularityEn  = 'month';
            $periodsLeft    = $monthsLeft ?? null;
        }

        $realRemaining  = $calculator->getRealRemainingBudget();
        $resteAVivre    = $calculator->getResteAVivre();
        $monthlySpend   = $calculator->getCurrentMonthVariableSpending();

        // Montant suggéré selon la granularité
        $maxSavingsPerMonth = $realRemaining * 0.30; // max 30% du budget réel
        $amountPerPeriod    = $periodsLeft
            ? min($remaining / $periodsLeft, $maxSavingsPerMonth / ($granularity === 'day' ? 30 : ($granularity === 'week' ? 4 : 1)))
            : null;

        $today       = now();
        $todayStr    = $today->toDateString();
        $currentYear = $today->year;

        $context = [
            'date_aujourdhui' => $todayStr,
            'annee_en_cours'  => $currentYear,
            'objectif' => [
                'label'              => $goal->label,
                'montant_cible'      => $goal->target_amount,
                'montant_actuel'     => $goal->current_amount,
                'montant_restant'    => $remaining,
                'date_cible'         => $targetDate?->format('Y-m-d'),
                'jours_restants'     => $daysLeft,
                'semaines_restantes' => $weeksLeft,
                'mois_restants'      => $monthsLeft,
                'granularite'        => $granularity, // 'day', 'week', ou 'month'
            ],
            'budget' => [
                'budget_reel_restant'   => $realRemaining,
                'reste_a_vivre'         => $resteAVivre,
                'depenses_ce_mois'      => $monthlySpend,
                'revenu_reference'      => $calculator->getSafeIncomeBaseline(),
                'max_epargne_mensuelle' => round($maxSavingsPerMonth),
                'suggestion_par_periode'=> $amountPerPeriod ? round($amountPerPeriod) : null,
            ],
        ];

        if ($locale === 'en') {
            $systemPrompt = "You are TemaCoach, a financial coach in Cameroon. All amounts are in FCFA (never euros).

TEMPORAL CONTEXT: today's date is {$todayStr} (current year: {$currentYear}). Any date you generate (date_estimee) MUST be after today and use the real current year — NEVER use a past year.

RULES:
1. The user has a TARGET DATE. Determine if it's realistic first.
2. If there are {$daysLeft} days left, suggest savings PER {$granularityEn} (not per month).
3. Never suggest more than 30% of the real remaining budget as savings.
4. If the goal is NOT achievable before the target date, say so clearly and give the closest realistic estimate.
5. Adapt the suggestion to the timeframe: days → per day, weeks → per week, months → per month.

Respond ONLY in JSON:
{
  \"realisable\": boolean,
  \"granularite\": \"{$granularityEn}\",
  \"montant_epargne_suggere\": number,
  \"duree_periods\": number,
  \"date_estimee\": \"string (month year)\",
  \"commentaire\": \"string (2 sentences max: achievable or not, and how much to save per {$granularityEn})\"
}";
        } else {
            $systemPrompt = "Tu es TemaCoach, un coach financier au Cameroun. Les montants sont en FCFA (jamais en euros).

CONTEXTE TEMPOREL : nous sommes aujourd'hui le {$todayStr} (année en cours : {$currentYear}). Toute date que tu génères (date_estimee) DOIT être postérieure à aujourd'hui et utiliser l'année réelle en cours — n'utilise JAMAIS une année passée.

RÈGLES :
1. L'utilisateur a une DATE CIBLE. Détermine d'abord si c'est réalisable.
2. Il reste {$daysLeft} jours — suggère une épargne PAR {$granularityFr} (pas par mois).
3. Ne jamais suggérer plus de 30% du budget réel restant comme épargne.
4. Si l'objectif N'EST PAS réalisable avant la date cible, dis-le clairement et donne l'estimation la plus proche possible.
5. Adapte la suggestion à la durée : jours → par jour, semaines → par semaine, mois → par mois.

Réponds UNIQUEMENT en JSON :
{
  \"realisable\": boolean,
  \"granularite\": \"{$granularityFr}\",
  \"montant_epargne_suggere\": number,
  \"duree_periods\": number,
  \"date_estimee\": \"string (mois année en français)\",
  \"commentaire\": \"string (2 phrases max : réalisable ou non, et combien épargner par {$granularityFr})\"
}";
        }

        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['verify' => config('services.anthropic.verify_ssl', true)])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 400,
                'system'     => $systemPrompt,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => json_encode($context, JSON_UNESCAPED_UNICODE),
                ]],
            ]);

            if ($response->failed()) {
                return response()->json(['estimation' => null, 'locked' => false, 'message' => 'Service temporairement indisponible.']);
            }

            $raw        = $response->json('content.0.text');
            $clean      = preg_replace('/```json|```/', '', $raw);
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