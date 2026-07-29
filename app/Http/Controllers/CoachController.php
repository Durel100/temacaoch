<?php

namespace App\Http\Controllers;

use App\Http\Services\FinancialCalculatorService;
use App\Http\Services\RecommendationEngineService;
use App\Models\ChatConversation;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Carbon\Carbon;

class CoachController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversation = ChatConversation::firstOrCreate(['user_id' => $user->id]);

        $messages = $conversation->messages()->orderBy('created_at')->get();

        return Inertia::render('Coach/Index', [
            'messages'       => $messages,
            'conversationId' => $conversation->id,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'message'         => 'required|string|max:1000',
            'conversation_id' => 'required|exists:chat_conversations,id',
        ]);

        $user   = $request->user();
        $locale = $user->locale ?? 'fr';

        $conversation = ChatConversation::where('id', $validated['conversation_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $conversation->messages()->create([
            'role'    => 'user',
            'content' => $validated['message'],
        ]);

        // Charger toutes les relations
        $user->load([
            'profile', 'dependents', 'incomeSources',
            'fixedCharges', 'debts', 'financialGoals', 'tontineGroups',
        ]);

        $calculator  = new FinancialCalculatorService($user);
        $recommender = new RecommendationEngineService($user);

        // ── Contexte financier de base ────────────────────────────────────────
        $context = [
            'revenu_reference'           => $calculator->getSafeIncomeBaseline(),
            'charges_fixes_total'        => $calculator->getTotalMonthlyFixedCharges(),
            'reste_a_vivre'              => $calculator->getResteAVivre(),
            'depenses_variables_ce_mois' => $calculator->getCurrentMonthVariableSpending(),
            'budget_reel_restant'        => $calculator->getRealRemainingBudget(),
            'jours_restants_mois'        => now()->daysInMonth - now()->day,
            'recommandations_actives'    => $recommender->getTopRecommendations(3),
            'dettes'    => $user->debts->map(fn ($d) => [
                'label'   => $d->label,
                'restant' => $d->remaining_amount,
                'taux'    => $d->interest_rate,
            ]),
            'objectifs' => $user->financialGoals->map(fn ($g) => [
                'label'       => $g->label,
                'cible'       => $g->target_amount,
                'actuel'      => $g->current_amount,
                'progression' => $g->progress_percent,
            ]),
            'tontines'  => $user->tontineGroups->where('is_active', true)->map(fn ($t) => [
                'nom'       => $t->name,
                'cotisation' => $t->contribution_amount,
            ]),
        ];

        // ── Statistiques du mois en cours ─────────────────────────────────────
        $now   = now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        $monthTransactions = $user->transactions()
            ->whereBetween('transacted_at', [$start, $end])
            ->with('category')
            ->get();

        $totalIn  = $monthTransactions->where('direction', 'in')->sum('amount');
        $totalOut = $monthTransactions->where('direction', 'out')->sum('amount');

        // Taux d'épargne
        $savingsRate = $totalIn > 0
            ? round((($totalIn - $totalOut) / $totalIn) * 100, 1)
            : 0;

        // Dépenses par catégorie ce mois
        $spendingByCategory = $monthTransactions
            ->where('direction', 'out')
            ->groupBy(fn ($t) => $t->category?->name ?? 'Sans catégorie')
            ->map(fn ($g) => [
                'categorie' => $g->first()->category?->name ?? 'Sans catégorie',
                'total'     => $g->sum('amount'),
                'nombre'    => $g->count(),
                'percent'   => $totalOut > 0 ? round(($g->sum('amount') / $totalOut) * 100) : 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->take(5)
            ->toArray();

        // Comparaison mois précédent
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd   = $now->copy()->subMonth()->endOfMonth();
        $prevTr    = $user->transactions()->whereBetween('transacted_at', [$prevStart, $prevEnd])->get();
        $prevOut   = $prevTr->where('direction', 'out')->sum('amount');
        $prevIn    = $prevTr->where('direction', 'in')->sum('amount');

        // Habitudes
        $daysElapsed = $now->day;
        $dailyAvgOut = $daysElapsed > 0 ? round($totalOut / $daysElapsed) : 0;

        // Prévision fin de mois
        $forecastOut = $daysElapsed > 0
            ? round($totalOut + ($dailyAvgOut * ($now->daysInMonth - $daysElapsed)))
            : null;

        // Jour le plus dépensier
        $busiestDay = $monthTransactions->where('direction', 'out')
            ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('amount'))
            ->sortDesc()->keys()->first();

        // Ajouter les stats au contexte
        $context['stats_ce_mois'] = [
            'total_entrees'         => $totalIn,
            'total_sorties'         => $totalOut,
            'solde_net'             => $totalIn - $totalOut,
            'taux_epargne'          => $savingsRate . '%',
            'depense_moyenne_jour'  => $dailyAvgOut,
            'prevision_fin_mois'    => $forecastOut,
            'vs_mois_precedent_out' => $prevOut > 0
                ? round((($totalOut - $prevOut) / $prevOut) * 100) . '%'
                : 'N/A',
            'vs_mois_precedent_in'  => $prevIn > 0
                ? round((($totalIn - $prevIn) / $prevIn) * 100) . '%'
                : 'N/A',
            'top_categories'        => $spendingByCategory,
            'jour_plus_depensier'   => $busiestDay,
        ];

        // ── Historique de la conversation ─────────────────────────────────────
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // ── Ton adapté au profil ───────────────────────────────────────────────
        $spendingTendency  = $user->profile?->spending_tendency          ?? 'depends';
        $struggleFrequency = $user->profile?->budget_struggle_frequency  ?? 'sometimes';

        $toneGuidance = match ($spendingTendency) {
            'spends_quickly' => $locale === 'en'
                ? "This user tends to spend quickly. Be firm but supportive."
                : "Cet utilisateur dépense vite. Sois ferme mais bienveillant.",
            'saves' => $locale === 'en'
                ? "This user naturally saves. Encourage their good habits."
                : "Cet utilisateur est naturellement économe. Encourage ses bonnes habitudes.",
            default => $locale === 'en'
                ? "This user is balanced. Stay neutral and informative."
                : "Cet utilisateur est équilibré. Reste neutre et informatif.",
        };

        $urgencyGuidance = match ($struggleFrequency) {
            'often' => $locale === 'en'
                ? "This user often struggles. Prioritize emergency fund."
                : "Cet utilisateur boucle souvent difficilement. Priorise le fonds d'urgence.",
            'sometimes' => $locale === 'en'
                ? "This user sometimes struggles. Suggest moderate precautions."
                : "Cet utilisateur boucle parfois difficilement. Suggère des précautions modérées.",
            default => $locale === 'en'
                ? "This user rarely struggles. Suggest growth goals."
                : "Cet utilisateur s'en sort bien. Suggère des objectifs de croissance.",
        };

        // ── System prompt complet avec stats ──────────────────────────────────
        $languageInstruction = $locale === 'en'
            ? "You MUST respond exclusively in English."
            : "Tu communiques exclusivement en français.";

        $actionSchema = '{
  "type": "create_goal" | "create_transaction",
  "label": "string",
  "amount": number,
  "target_date": "YYYY-MM-DD (optionnel pour objectif)"
}';

        if ($locale === 'en') {
            $systemPrompt = "You are TemaCoach, a personal financial coach for users in Cameroon.
{$languageInstruction}
{$toneGuidance}
{$urgencyGuidance}

Here is the user's complete financial situation:
" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

ABSOLUTE RULES:
- NEVER use markdown formatting: no **bold**, no ## headers, no --- separators, no tables with |.
- Write in plain conversational text only.
- NEVER invent figures. Use ONLY those provided in the context.
- If information is missing, say so clearly.
- NEVER give investment advice (stocks, crypto).
- Always mention amounts in the format: 150,000 FCFA.
- Keep responses concise (max 150 words) unless the user asks for detail.
- You may ask ONE follow-up question if relevant.

ACTION BUTTONS:
If the user mentions a goal or project they want to create, OR asks to record a transaction, add at the very end of your response (after a blank line) an action block like this:
<action>" . json_encode(['type' => 'create_goal', 'label' => 'Example goal', 'amount' => 50000, 'target_date' => null], JSON_UNESCAPED_UNICODE) . "</action>

Use type 'create_goal' for savings goals/projects. Use type 'create_transaction' for expenses or income to record.
Only include an action block when the user explicitly mentions creating something or recording a transaction.";
        } else {
            $systemPrompt = "Tu es TemaCoach, un coach financier personnel pour des utilisateurs au Cameroun.
{$languageInstruction}
{$toneGuidance}
{$urgencyGuidance}

Voici la situation financière complète de l'utilisateur :
" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

RÈGLES ABSOLUES :
- N'utilise JAMAIS de formatage markdown : pas de **gras**, pas de ## titres, pas de --- séparateurs, pas de tableaux avec |.
- Écris en texte conversationnel simple uniquement.
- Ne JAMAIS inventer de chiffres. Utilise UNIQUEMENT ceux fournis dans le contexte.
- Si une information manque, dis-le clairement.
- Ne donne JAMAIS de conseils d'investissement (bourse, crypto).
- Mentionne toujours les montants au format : 150 000 FCFA.
- Réponds de manière concise (max 150 mots) sauf si l'utilisateur demande plus de détails.
- Si le taux d'épargne est négatif ou faible, signale-le et propose des actions concrètes.
- Tu peux poser UNE seule question de suivi si c'est pertinent.

BOUTONS D'ACTION :
Si l'utilisateur mentionne un objectif ou projet qu'il veut créer, OU demande à enregistrer une transaction, ajoute à la toute fin de ta réponse (après une ligne vide) un bloc action comme ceci :
<action>" . json_encode(['type' => 'create_goal', 'label' => 'Exemple objectif', 'amount' => 50000, 'target_date' => null], JSON_UNESCAPED_UNICODE) . "</action>

Utilise type 'create_goal' pour les objectifs/projets d'épargne. Utilise type 'create_transaction' pour les dépenses ou revenus à enregistrer.
N'inclus un bloc action QUE si l'utilisateur mentionne explicitement vouloir créer quelque chose ou enregistrer une transaction.";
        }

        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['verify' => config('services.anthropic.verify_ssl')])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 600,
                'system'     => $systemPrompt,
                'messages'   => $history,
            ]);

            if ($response->failed()) {
                $errorBody = $response->json();
                return response()->json([
                    'error' => 'Erreur API : ' . ($errorBody['error']['message'] ?? 'Inconnue')
                ], 500);
            }

            $aiResponse = $response->json('content.0.text');

            if (!$aiResponse) {
                return response()->json(['error' => 'Réponse vide de l\'API.'], 500);
            }

            $assistantMessage = $conversation->messages()->create([
                'role'             => 'assistant',
                'content'          => $aiResponse,
                'context_snapshot' => $context,
            ]);

            return response()->json(['message' => $assistantMessage]);

        } catch (\Exception $e) {
            \Log::error('Coach error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Exception : ' . $e->getMessage()], 500);
        }
    }

    public function clear(Request $request)
    {
        $user = $request->user();
        ChatConversation::where('user_id', $user->id)->first()?->messages()->delete();
        return back()->with('success', 'Conversation effacée.');
    }
}