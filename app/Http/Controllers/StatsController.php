<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $user   = $request->user();
        $period = $request->get('period', 'month');
        $year   = (int) $request->get('year',  now()->year);
        $month  = (int) $request->get('month', now()->month);

        [$startDate, $endDate] = $this->getDateRange($period, $year, $month);

        $transactions = $user->transactions()
            ->whereBetween('transacted_at', [$startDate, $endDate])
            ->with('category')
            ->orderByDesc('transacted_at')
            ->get();

        $totalIn    = $transactions->where('direction', 'in')->sum('amount');
        $totalOut   = $transactions->where('direction', 'out')->sum('amount');
        $balance    = $totalIn - $totalOut;
        $totalCount = $transactions->count();

        // Taux d'epargne
        $savingsRate = $totalIn > 0
            ? round((($totalIn - $totalOut) / $totalIn) * 100, 1)
            : 0;

        // Comparaison mois precedent
        $comparison = null;
        if ($period === 'month') {
            $prevStart = Carbon::create($year, $month, 1)->subMonth()->startOfMonth();
            $prevEnd   = Carbon::create($year, $month, 1)->subMonth()->endOfMonth();
            $prevTr    = $user->transactions()->whereBetween('transacted_at', [$prevStart, $prevEnd])->get();
            $prevIn    = $prevTr->where('direction', 'in')->sum('amount');
            $prevOut   = $prevTr->where('direction', 'out')->sum('amount');

            $comparison = [
                'prev_label'   => Carbon::create($year, $month, 1)->subMonth()->translatedFormat('F Y'),
                'prev_in'      => $prevIn,
                'prev_out'     => $prevOut,
                'diff_in'      => $totalIn  - $prevIn,
                'diff_out'     => $totalOut - $prevOut,
                'diff_in_pct'  => $prevIn  > 0 ? round((($totalIn  - $prevIn)  / $prevIn)  * 100) : null,
                'diff_out_pct' => $prevOut > 0 ? round((($totalOut - $prevOut) / $prevOut) * 100) : null,
            ];
        }

        // Detail jour par jour
        $byDayDetail = [];
        if (in_array($period, ['month', 'week', 'today'])) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $dayTr = $transactions->filter(
                    fn ($t) => Carbon::parse($t->transacted_at)->toDateString() === $current->toDateString()
                );
                if ($dayTr->isNotEmpty()) {
                    $byDayDetail[] = [
                        'date'       => $current->toDateString(),
                        'label'      => $current->translatedFormat('D d M'),
                        'in'         => $dayTr->where('direction', 'in')->sum('amount'),
                        'out'        => $dayTr->where('direction', 'out')->sum('amount'),
                        'count'      => $dayTr->count(),
                        'is_weekend' => $current->isWeekend(),
                        'items'      => $dayTr->map(fn ($t) => [
                            'amount'    => $t->amount,
                            'direction' => $t->direction,
                            'category'  => $t->category?->name,
                            'note'      => $t->note,
                        ])->values()->toArray(),
                    ];
                }
                $current->addDay();
            }
        }

        // Habitudes par jour de la semaine
        $locale      = $user->locale ?? 'fr';
        $dayNamesMap = $locale === 'en'
            ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
            : ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        $habitsByDay = [];
        $outTrAll = $transactions->where('direction', 'out');
        for ($d = 0; $d <= 6; $d++) {
            $group = $outTrAll->filter(
                fn ($t) => Carbon::parse($t->transacted_at)->dayOfWeek === $d
            );
            $habitsByDay[] = [
                'day'   => $dayNamesMap[$d],
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        }

        // Heure la plus frequente
        $outTransactions = $transactions->where('direction', 'out');
        $byHour = $outTransactions->isNotEmpty()
            ? $outTransactions
                ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->format('H'))
                ->map(fn ($g) => $g->count())
                ->sortDesc()->keys()->first()
            : null;

        // Jour le plus depensier
        $busiestDay = $transactions->where('direction', 'out')
            ->groupBy(fn ($t) => Carbon::parse($t->transacted_at)->format('Y-m-d'))
            ->map(fn ($g) => [
                'date'  => Carbon::parse($g->first()->transacted_at)->translatedFormat('l d M'),
                'total' => $g->sum('amount'),
                'count' => $g->count(),
            ])
            ->sortByDesc('total')->first();

        // Prevision fin de mois
        $forecast = null;
        if ($period === 'month' && now()->month === $month && now()->year === $year) {
            $daysElapsed   = now()->day;
            $daysRemaining = now()->daysInMonth - $daysElapsed;

            if ($daysElapsed > 0) {
                $dailyAvgOut = $totalOut / $daysElapsed;
                $dailyAvgIn  = $totalIn  / $daysElapsed;
                $fOut        = round($totalOut + ($dailyAvgOut * $daysRemaining));
                $fIn         = round($totalIn  + ($dailyAvgIn  * $daysRemaining));

                $forecast = [
                    'days_elapsed'          => $daysElapsed,
                    'days_remaining'        => $daysRemaining,
                    'daily_avg_out'         => round($dailyAvgOut),
                    'daily_avg_in'          => round($dailyAvgIn),
                    'forecast_out'          => $fOut,
                    'forecast_in'           => $fIn,
                    'forecast_balance'      => $fIn - $fOut,
                    'forecast_savings_rate' => $fIn > 0
                        ? round((($fIn - $fOut) / $fIn) * 100, 1)
                        : 0,
                ];
            }
        }

        // Depenses par categorie
        $byCategory = $transactions->where('direction', 'out')
            ->groupBy(fn ($t) => $t->category?->name ?? 'Sans catégorie')
            ->map(fn ($g) => [
                'name'            => $g->first()->category?->name ?? 'Sans catégorie',
                'translation_key' => $g->first()->category?->translation_key,
                'total'           => $g->sum('amount'),
                'count'           => $g->count(),
                'percent'         => $totalOut > 0 ? round(($g->sum('amount') / $totalOut) * 100) : 0,
            ])
            ->sortByDesc('total')->values();

        // Sources de revenus
        $byIncomeSource = $transactions->where('direction', 'in')
            ->groupBy(fn ($t) => $t->category?->name ?? 'Autre')
            ->map(fn ($g) => [
                'name'            => $g->first()->category?->name ?? 'Autre',
                'translation_key' => $g->first()->category?->translation_key,
                'total'           => $g->sum('amount'),
                'percent'         => $totalIn > 0 ? round(($g->sum('amount') / $totalIn) * 100) : 0,
            ])
            ->sortByDesc('total')->values();

        $biggestExpense = $transactions->where('direction', 'out')->sortByDesc('amount')->first();
        $biggestIncome  = $transactions->where('direction', 'in')->sortByDesc('amount')->first();

        return Inertia::render('Stats/Index', [
            'period'           => $period,
            'year'             => $year,
            'month'            => $month,
            'startDate'        => $startDate->toDateString(),
            'endDate'          => $endDate->toDateString(),
            'totalIn'          => $totalIn,
            'totalOut'         => $totalOut,
            'balance'          => $balance,
            'totalCount'       => $totalCount,
            'savingsRate'      => $savingsRate,
            'comparison'       => $comparison,
            'byDayDetail'      => $byDayDetail,
            'habitsByDay'      => $habitsByDay,
            'forecast'         => $forecast,
            'biggestExpense'   => $biggestExpense ? [
                'amount'          => $biggestExpense->amount,
                'category'        => $biggestExpense->category?->name,
                'translation_key' => $biggestExpense->category?->translation_key,
                'date'            => Carbon::parse($biggestExpense->transacted_at)->translatedFormat('d M Y à H\hi'),
                'note'            => $biggestExpense->note,
            ] : null,
            'biggestIncome'    => $biggestIncome ? [
                'amount'          => $biggestIncome->amount,
                'category'        => $biggestIncome->category?->name,
                'translation_key' => $biggestIncome->category?->translation_key,
                'date'            => Carbon::parse($biggestIncome->transacted_at)->translatedFormat('d M Y à H\hi'),
            ] : null,
            'byCategory'       => $byCategory,
            'byIncomeSource'   => $byIncomeSource,
            'monthlyEvolution' => $this->getMonthlyEvolution($user),
            'dailyEvolution'   => $this->getDailyEvolution($user, $startDate, $endDate),
            'busiestDay'       => $busiestDay,
            'busiestHour'      => $byHour !== null ? (int) $byHour : null,
            'transactions'     => $transactions->map(fn ($t) => [
                'id'              => $t->id,
                'amount'          => $t->amount,
                'direction'       => $t->direction,
                'category'        => $t->category?->name,
                'translation_key' => $t->category?->translation_key,
                'transacted_at'   => $t->transacted_at,
                'note'            => $t->note,
                'source'          => $t->source,
            ])->values(),
        ]);
    }

    private function getDateRange(string $period, int $year, int $month): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(),   now()->endOfDay()],
            'week'  => [now()->startOfWeek(),  now()->endOfWeek()],
            'month' => [
                Carbon::create($year, $month, 1)->startOfMonth(),
                Carbon::create($year, $month, 1)->endOfMonth(),
            ],
            'year'  => [
                Carbon::create($year, 1, 1)->startOfYear(),
                Carbon::create($year, 1, 1)->endOfYear(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function getMonthlyEvolution($user): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();

            $tr  = $user->transactions()->whereBetween('transacted_at', [$start, $end])->get();
            $in  = $tr->where('direction', 'in')->sum('amount');
            $out = $tr->where('direction', 'out')->sum('amount');

            $months[] = [
                'label'        => $date->translatedFormat('M Y'),
                'short'        => $date->translatedFormat('M'),
                'in'           => $in,
                'out'          => $out,
                'balance'      => $in - $out,
                'savings_rate' => $in > 0 ? round((($in - $out) / $in) * 100) : 0,
            ];
        }
        return $months;
    }

    private function getDailyEvolution($user, Carbon $start, Carbon $end): array
    {
        $days    = [];
        $current = $start->copy();

        while ($current <= $end) {
            $tr = $user->transactions()->whereDate('transacted_at', $current->toDateString())->get();
            $days[] = [
                'label' => $current->translatedFormat('d M'),
                'date'  => $current->toDateString(),
                'in'    => $tr->where('direction', 'in')->sum('amount'),
                'out'   => $tr->where('direction', 'out')->sum('amount'),
            ];
            $current->addDay();
        }

        return array_slice($days, 0, 31);
    }
}