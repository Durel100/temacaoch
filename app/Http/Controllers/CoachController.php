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

        // ── Contexte financier complet ────────────────────────────────────────
        $resteAVivre   = $calculator->getResteAVivre();
        $realRemaining = $calculator->getRealRemainingBudget();
        $totalIn_ctx   = $calculator->getCurrentMonthTransactionsIn();
        $totalOut_ctx  = $calculator->getCurrentMonthVariableSpending();

        // Tontines avec prochaine date de cotisation
        $tontinesDetail = $user->tontineGroups->where('is_active', true)->map(function ($t) {
            $nextCycle = $t->nextCycle();
            $myPositions = is_array($t->my_positions) ? $t->my_positions : json_decode($t->my_positions ?? '[]', true);
            $nbPositions = count($myPositions) ?: 1;
            return [
                'nom'                    => $t->name,
                'cotisation_par_nom'     => $t->contribution_amount,
                'nb_noms'                => $nbPositions,
                'cotisation_totale'      => $t->contribution_amount * $nbPositions,
                'prochaine_date'         => $nextCycle?->scheduled_date,
                'jours_avant_cotisation' => $nextCycle
                    ? max(0, (int) now()->diffInDays(\Carbon\Carbon::parse($nextCycle->scheduled_date), false))
                    : null,
                'mon_tour_reception'     => $t->myPayoutCycle()?->scheduled_date,
            ];
        });

        // Charges fixes avec statut paiement ce mois
        $chargesDetail = $user->fixedCharges->where('is_active', true)->map(function ($c) {
            $paidThisMonth = \App\Models\Transaction::where('user_id', $c->user_id)
                ->where('fixed_charge_id', $c->id)
                ->whereMonth('transacted_at', now()->month)
                ->whereYear('transacted_at', now()->year)
                ->sum('amount');
            return [
                'label'          => $c->label,
                'montant'        => $c->monthly_equivalent,
                'paye_ce_mois'   => $paidThisMonth,
                'reste_a_payer'  => max(0, $c->monthly_equivalent - $paidThisMonth),
                'est_paye'       => $paidThisMonth >= $c->monthly_equivalent,
            ];
        });

        $context = [
            'date_aujourdhui'            => now()->translatedFormat('l d F Y'),
            'jour_paye'                  => $user->profile?->salary_day,
            'revenu_reference'           => $calculator->getSafeIncomeBaseline(),
            'solde_declare_onboarding'   => $resteAVivre,
            'charges_fixes_total'        => $calculator->getTotalMonthlyFixedCharges(),
            'budget_reel_restant'        => $realRemaining,
            'total_entrees_ce_mois'      => $totalIn_ctx,
            'total_sorties_ce_mois'      => $totalOut_ctx,
            'solde_reel'                 => $resteAVivre + $totalIn_ctx - $totalOut_ctx,
            'jours_restants_mois'        => $calculator->getDaysLeftInFinancialMonth(),
            'recommandations_actives'    => $recommender->getTopRecommendations(3),
            'dettes'    => $user->debts->where('remaining_amount', '>', 0)->map(fn ($d) => [
                'label'   => $d->label,
                'restant' => $d->remaining_amount,
                'taux'    => $d->interest_rate,
            ]),
            'objectifs' => $user->financialGoals->where('is_archived', false)->map(fn ($g) => [
                'label'       => $g->label,
                'cible'       => $g->target_amount,
                'actuel'      => $g->current_amount,
                'progression' => $g->progress_percent . '%',
                'echeance'    => $g->target_date?->translatedFormat('d M Y'),
            ]),
            'tontines'      => $tontinesDetail,
            'charges_fixes' => $chargesDetail,
        ];

        // ── Statistiques du cycle financier en cours ─────────────────────────
        // Aligné sur le cycle salary_day (cohérent avec le dashboard), pas sur
        // le mois calendaire.
        $now   = now();
        $start = $calculator->getFinancialCycleStart();
        $end   = $calculator->getFinancialCycleEnd(); // borne exclusive

        $monthTransactions = $user->transactions()
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<', $end)
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

        // Comparaison cycle précédent
        $prevEnd   = $start;                                              // fin exclusive = début du cycle courant
        $prevStart = $calculator->getFinancialCycleStart($start->copy()->subDay());
        $prevTr    = $user->transactions()
            ->where('transacted_at', '>=', $prevStart)
            ->where('transacted_at', '<', $prevEnd)
            ->get();
        $prevOut   = $prevTr->where('direction', 'out')->sum('amount');
        $prevIn    = $prevTr->where('direction', 'in')->sum('amount');

        // Habitudes (rapportées au cycle, pas au mois calendaire)
        $cycleLength = max(1, (int) $start->diffInDays($end));
        $daysElapsed = max(1, (int) $start->diffInDays($now) + 1);
        $daysElapsed = min($daysElapsed, $cycleLength);
        $daysLeft    = max(0, $cycleLength - $daysElapsed);
        $dailyAvgOut = round($totalOut / $daysElapsed);

        // Prévision fin de cycle
        $forecastOut = round($totalOut + ($dailyAvgOut * $daysLeft));

        // Jour le plus dépensier
        $busiestDay = $monthTransactions->where('direction', 'out')
            ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('amount'))
            ->sortDesc()->keys()->first();

        // Ajouter les stats au contexte
        $context['stats_ce_mois'] = [
            'total_entrees'         => $totalIn,
            'total_sorties'         => $totalOut,
            'solde_net'             => $resteAVivre + $totalIn - $totalOut,
            'solde_net_transactions' => $totalIn - $totalOut,
            'taux_epargne'          => $savingsRate . '%',
            'depense_moyenne_jour'  => $dailyAvgOut,
            'vs_mois_precedent_out' => $prevOut > 0
                ? round((($totalOut - $prevOut) / $prevOut) * 100) . '%'
                : 'N/A',
            'vs_mois_precedent_in'  => $prevIn > 0
                ? round((($totalIn - $prevIn) / $prevIn) * 100) . '%'
                : 'N/A',
            'top_categories'        => $spendingByCategory,
            'jour_plus_depensier'   => $busiestDay,
        ];

        // ── Stats de la période demandée dans le message ─────────────────────
        $periodStats = $this->extractPeriodStats($user, $validated['message']);
        if ($periodStats) {
            $context['stats_periode_demandee'] = $periodStats;
        }

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
- If stats_periode_demandee is present in the context, use that data to answer the question about the requested period. NEVER say you don't have the info if it's in the context.

ACTION BUTTONS:
If the user explicitly mentions wanting to create or add something, add ONE action block at the very end of your response (after a blank line).

For a savings goal or project:
<action>" . json_encode(['type' => 'create_goal', 'label' => 'Goal name', 'amount' => 50000, 'target_date' => null], JSON_UNESCAPED_UNICODE) . "</action>

For a recurring fixed charge (rent, subscription, loan payment, etc.):
<action>" . json_encode(['type' => 'create_fixed_charge', 'label' => 'Charge name', 'amount' => 10000, 'frequency' => 'monthly'], JSON_UNESCAPED_UNICODE) . "</action>

For a one-time expense or income to record:
<action>" . json_encode(['type' => 'create_transaction', 'label' => 'Description', 'amount' => 5000, 'direction' => 'out'], JSON_UNESCAPED_UNICODE) . "</action>

Only include ONE action block and only when the user explicitly mentions creating or adding something.";
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
- Si stats_periode_demandee est présent dans le contexte, utilise ces données pour répondre précisément à la question sur la période demandée. Ne dis JAMAIS que tu n'as pas l'info si elle est dans le contexte.

BOUTONS D'ACTION :
Si l'utilisateur mentionne explicitement vouloir créer ou ajouter quelque chose, ajoute UN seul bloc action à la toute fin de ta réponse (après une ligne vide).

Pour un objectif ou projet d'épargne :
<action>" . json_encode(['type' => 'create_goal', 'label' => 'Nom objectif', 'amount' => 50000, 'target_date' => null], JSON_UNESCAPED_UNICODE) . "</action>

Pour une charge fixe récurrente (loyer, abonnement, remboursement, etc.) :
<action>" . json_encode(['type' => 'create_fixed_charge', 'label' => 'Nom charge', 'amount' => 10000, 'frequency' => 'monthly'], JSON_UNESCAPED_UNICODE) . "</action>

Pour une dépense ou revenu ponctuel à enregistrer :
<action>" . json_encode(['type' => 'create_transaction', 'label' => 'Description', 'amount' => 5000, 'direction' => 'out'], JSON_UNESCAPED_UNICODE) . "</action>

N'inclus QU'UN SEUL bloc action et seulement si l'utilisateur mentionne explicitement vouloir créer ou ajouter quelque chose.";
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

            // ── Bug 4 : extraire le bloc <action>...</action> et le retirer du texte ──
            // Le flag /s permet au . de matcher les sauts de ligne éventuels.
            $action = null;
            if (preg_match('/<action>\s*(\{.*?\})\s*<\/action>/s', $aiResponse, $am)) {
                $decoded = json_decode($am[1], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['type'])) {
                    $action = $decoded;
                }
            }

            // On retire TOUJOURS le(s) bloc(s) action du texte affiché,
            // même si le JSON est malformé, pour ne rien laisser fuiter dans la bulle.
            $cleanText = preg_replace('/<action>.*?<\/action>/s', '', $aiResponse);
            $cleanText = trim($cleanText);

            $assistantMessage = $conversation->messages()->create([
                'role'             => 'assistant',
                'content'          => $cleanText,
                'action'           => $action,
                'context_snapshot' => $context,
            ]);

            return response()->json([
                'message' => $assistantMessage,
                'action'  => $action,
            ]);

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

    private function extractPeriodStats($user, string $message): ?array
    {
        $now   = now();
        $start = null;
        $end   = null;
        $label = null;

        $monthNames = [
            'janvier'=>1,'février'=>2,'fevrier'=>2,'mars'=>3,'avril'=>4,
            'mai'=>5,'juin'=>6,'juillet'=>7,'août'=>8,'aout'=>8,
            'septembre'=>9,'octobre'=>10,'novembre'=>11,'décembre'=>12,'decembre'=>12,
            'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
            'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
        ];

        if (preg_match('/mois dernier|last month/i', $message)) {
            $start = $now->copy()->subMonth()->startOfMonth();
            $end   = $now->copy()->subMonth()->endOfMonth();
            $label = $start->translatedFormat('F Y');
        } elseif (preg_match('/semaine derni[eè]re|last week/i', $message)) {
            $start = $now->copy()->subWeek()->startOfWeek();
            $end   = $now->copy()->subWeek()->endOfWeek();
            $label = 'semaine du ' . $start->translatedFormat('d M') . ' au ' . $end->translatedFormat('d M');
        } elseif (preg_match('/cette semaine|this week/i', $message)) {
            $start = $now->copy()->startOfWeek();
            $end   = $now->copy()->endOfWeek();
            $label = 'cette semaine';
        } elseif (preg_match('/du\s+(\d+)\w*\s+au\s+(\d+)\w*\s+(\w+)/ui', $message, $m)) {
            $monthNum = $monthNames[strtolower($m[3])] ?? null;
            if ($monthNum) {
                $year  = preg_match('/\b(202\d)\b/', $message, $ym) ? (int)$ym[1] : $now->year;
                $start = \Carbon\Carbon::create($year, $monthNum, (int)$m[1])->startOfDay();
                $end   = \Carbon\Carbon::create($year, $monthNum, (int)$m[2])->endOfDay();
                $label = "du {$m[1]} au {$m[2]} {$m[3]}";
            }
        } else {
            foreach ($monthNames as $name => $num) {
                // Bornes Unicode : "mai" ne doit pas matcher dans "jamais", etc.
                if (preg_match('/(?<!\p{L})' . preg_quote($name, '/') . '(?!\p{L})/iu', $message)) {
                    $year  = preg_match('/\b(202\d)\b/', $message, $ym) ? (int)$ym[1] : $now->year;
                    $start = \Carbon\Carbon::create($year, $num, 1)->startOfMonth();
                    $end   = \Carbon\Carbon::create($year, $num, 1)->endOfMonth();
                    $label = $start->translatedFormat('F Y');
                    break;
                }
            }
        }

        if (!$start || !$end) return null;

        $tr  = $user->transactions()
            ->whereBetween('transacted_at', [$start, $end])
            ->with('category')
            ->get();

        $in  = $tr->where('direction', 'in')->sum('amount');
        $out = $tr->where('direction', 'out')->sum('amount');

        $byCategory = $tr->where('direction', 'out')
            ->groupBy(fn ($t) => $t->category?->name ?? 'Autre')
            ->map(fn ($g) => [
                'categorie' => $g->first()->category?->name ?? 'Autre',
                'total'     => $g->sum('amount'),
                'nombre'    => $g->count(),
            ])
            ->sortByDesc('total')->take(5)->values()->toArray();

        $byIncome = $tr->where('direction', 'in')
            ->groupBy(fn ($t) => $t->category?->name ?? 'Revenu')
            ->map(fn ($g) => [
                'source' => $g->first()->category?->name ?? 'Revenu',
                'total'  => $g->sum('amount'),
            ])
            ->sortByDesc('total')->values()->toArray();

        return [
            'periode'         => $label ?? ($start->toDateString() . ' au ' . $end->toDateString()),
            'total_entrees'   => $in,
            'total_sorties'   => $out,
            'solde_net'       => $in - $out,
            'taux_epargne'    => $in > 0 ? round((($in - $out) / $in) * 100, 1) . '%' : '0%',
            'nb_transactions' => $tr->count(),
            'sources_revenus' => $byIncome,
            'top_categories'  => $byCategory,
        ];
    }
}