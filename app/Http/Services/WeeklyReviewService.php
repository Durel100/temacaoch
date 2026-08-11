<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class WeeklyReviewService
{
    public function __construct(private User $user) {}

    /**
     * Bilan de la semaine, généré une seule fois puis mis en cache.
     *
     * @param Carbon|null $weekStart  lundi de la semaine visée (défaut : semaine en cours)
     * @param bool        $forceRefresh  régénère même si un cache existe
     */
    public function forWeek(?Carbon $weekStart = null, bool $forceRefresh = false): array
    {
        $weekStart = ($weekStart ? $weekStart->copy() : now())->startOfWeek()->startOfDay();
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $existing = WeeklyReview::where('user_id', $this->user->id)
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();

        if ($existing && !$forceRefresh) {
            return $existing->payload;
        }

        $context = $this->buildContext($weekStart, $weekEnd);

        // Pas de transaction cette semaine : état vide, sans appel API.
        $review = $context['nombre_transactions'] === 0
            ? $this->emptyState()
            : $this->generate($context);

        WeeklyReview::updateOrCreate(
            ['user_id' => $this->user->id, 'week_start' => $weekStart->toDateString()],
            ['payload' => $review, 'generated_at' => now()]
        );

        return $review;
    }

    /**
     * Assemble les chiffres de la semaine à partir des données existantes.
     */
    private function buildContext(Carbon $start, Carbon $end): array
    {
        $calculator = new FinancialCalculatorService($this->user);

        $tx = $this->user->transactions()
            ->where('transacted_at', '>=', $start)
            ->where('transacted_at', '<=', $end)
            ->with('category')
            ->get();

        $in  = (float) $tx->where('direction', 'in')->sum('amount');
        $out = (float) $tx->where('direction', 'out')->sum('amount');

        $byCategory = $tx->where('direction', 'out')
            ->groupBy('category.name')
            ->map(fn ($g) => (float) $g->sum('amount'))
            ->sortDesc()
            ->toArray();

        // Semaine précédente (dépenses) pour l'évolution
        $prevStart = $start->copy()->subWeek();
        $prevEnd   = $end->copy()->subWeek();
        $prevOut = (float) $this->user->transactions()
            ->where('direction', 'out')
            ->where('transacted_at', '>=', $prevStart)
            ->where('transacted_at', '<=', $prevEnd)
            ->sum('amount');

        // Jour le plus dépensier de la semaine
        $byDay = $tx->where('direction', 'out')
            ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->translatedFormat('l'))
            ->map(fn ($g) => (float) $g->sum('amount'))
            ->sortDesc();

        $profile = $this->user->profile;

        return [
            'semaine' => [
                'debut' => $start->translatedFormat('d M'),
                'fin'   => $end->translatedFormat('d M Y'),
            ],
            'entrees_semaine'             => $in,
            'depenses_semaine'            => $out,
            'solde_semaine'               => $in - $out,
            'depenses_par_categorie'      => $byCategory,
            'depenses_semaine_precedente' => $prevOut,
            'evolution_depenses_pct'      => $prevOut > 0 ? round((($out - $prevOut) / $prevOut) * 100) : null,
            'jour_le_plus_depensier'      => $byDay->keys()->first(),
            'nombre_transactions'         => $tx->count(),
            'budget_reel_restant'         => round($calculator->getRealRemainingBudget()),
            'jours_restants_cycle'        => $calculator->getDaysLeftInFinancialMonth(),
            'categories_en_depassement'   => $calculator->getOverspendingCategories(),
            'prochaine_tontine'           => $calculator->getUpcomingTontinePayout(),
            'objectifs'                   => $this->user->financialGoals()
                ->where('is_archived', false)
                ->get()
                ->map(fn ($g) => [
                    'label'            => $g->label,
                    'progress_percent' => $g->progress_percent,
                    'restant'          => max(0, $g->target_amount - $g->current_amount),
                ])->values()->toArray(),
            'habitudes' => [
                'tendance'   => $profile?->spending_tendency,
                'preference' => $profile?->budget_preference,
            ],
        ];
    }

    /**
     * Appelle le coach (Anthropic) et renvoie le bilan structuré.
     */
    private function generate(array $context): array
    {
        $locale = $this->user->locale ?? 'fr';

        $systemPrompt = $locale === 'en'
            ? $this->promptEn()
            : $this->promptFr();

        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['verify' => config('services.anthropic.verify_ssl', true)])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 900,
                'system'     => $systemPrompt,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => json_encode($context, JSON_UNESCAPED_UNICODE),
                ]],
            ]);

            if ($response->failed()) {
                return $this->fallback($context);
            }

            $raw   = $response->json('content.0.text');
            $clean = trim(preg_replace('/```json|```/', '', (string) $raw));
            $data  = json_decode($clean, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $this->fallback($context);
            }

            $data['genere_par_ia'] = true;

            return $data;
        } catch (\Throwable $e) {
            return $this->fallback($context);
        }
    }

    /**
     * Bilan minimal calculé sans IA (si l'API échoue) — l'utilisateur voit quand même ses chiffres.
     */
    private function fallback(array $c): array
    {
        return [
            'resume' => sprintf(
                'Cette semaine : %s FCFA entrés, %s FCFA dépensés (solde %s FCFA).',
                number_format($c['entrees_semaine'], 0, ',', ' '),
                number_format($c['depenses_semaine'], 0, ',', ' '),
                number_format($c['solde_semaine'], 0, ',', ' ')
            ),
            'points_positifs' => [],
            'alertes'         => [],
            'conseils'        => [],
            'opportunites'    => [],
            'genere_par_ia'   => false,
        ];
    }

    private function emptyState(): array
    {
        return [
            'resume'          => "Aucune transaction cette semaine. Enregistre tes dépenses et entrées pour recevoir ton bilan.",
            'points_positifs' => [],
            'alertes'         => [],
            'conseils'        => [],
            'opportunites'    => [],
            'genere_par_ia'   => false,
        ];
    }

    private function promptFr(): string
    {
        return <<<'TXT'
Tu es TemaCoach, un coach financier bienveillant au Cameroun. Les montants sont en FCFA (jamais en euros).

On te donne les chiffres de la semaine d'un utilisateur (dépenses, entrées, catégories, habitudes, objectifs). Rédige un bilan hebdomadaire court, encourageant et concret. Jamais moralisateur ni culpabilisant.

RÈGLES pour "opportunites" :
- Uniquement des opportunités tirées des données de l'utilisateur : économie réaliste sur une catégorie où il dépense beaucoup, montant à mettre sur un objectif, tontine à venir, surplus de budget à épargner.
- INTERDIT : produits financiers externes, placements, crédits, investissements, cryptomonnaies, ou toute recommandation de produit. Tu n'es pas un conseiller en investissement.
- Chaque opportunité doit être actionnable cette semaine ou ce mois-ci.

Réponds UNIQUEMENT en JSON, sans texte autour, sans balises Markdown :
{
  "resume": "2 à 3 phrases résumant la semaine",
  "points_positifs": ["1 à 2 éléments positifs concrets"],
  "alertes": ["0 à 2 points de vigilance, factuels et doux"],
  "conseils": ["1 à 2 conseils actionnables"],
  "opportunites": [{"titre": "court", "detail": "1 phrase, chiffrée si possible"}]
}
TXT;
    }

    private function promptEn(): string
    {
        return <<<'TXT'
You are TemaCoach, a supportive financial coach in Cameroon. All amounts are in FCFA (never euros).

You are given a user's weekly figures (spending, income, categories, habits, goals). Write a short, encouraging, concrete weekly review. Never preachy or shaming.

RULES for "opportunites":
- Only opportunities derived from the user's own data: a realistic saving on a category where they spend a lot, an amount to put toward a goal, an upcoming tontine, budget surplus to save.
- FORBIDDEN: external financial products, investments, loans, crypto, or any product recommendation. You are not an investment advisor.
- Each opportunity must be actionable this week or this month.

Respond ONLY in JSON, no surrounding text, no Markdown fences:
{
  "resume": "2-3 sentences summarizing the week",
  "points_positifs": ["1-2 concrete positives"],
  "alertes": ["0-2 gentle, factual watch-outs"],
  "conseils": ["1-2 actionable tips"],
  "opportunites": [{"titre": "short", "detail": "1 sentence, with figures if possible"}]
}
TXT;
    }
}