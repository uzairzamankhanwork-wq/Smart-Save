<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use App\Services\FinanceSummary;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const RANGES = [
        '3m' => 'Last 3 months',
        '6m' => 'Last 6 months',
        '12m' => 'Last 12 months',
        'ytd' => 'This year',
    ];

    public function __invoke(Request $request, FinanceSummary $summary): View
    {
        $user = $request->user();
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '6m';

        $to = now()->endOfMonth();
        $from = match ($range) {
            '3m' => now()->subMonthsNoOverflow(2)->startOfMonth(),
            '12m' => now()->subMonthsNoOverflow(11)->startOfMonth(),
            'ytd' => now()->startOfYear(),
            default => now()->subMonthsNoOverflow(5)->startOfMonth(),
        };

        $overview = $summary->overview($user);
        $series = $summary->monthlySeries($user, $from, $to);
        $rangeTotals = $summary->savingsBetween($user, $from, $to);

        $distribution = $overview['goals']
            ->filter(fn ($g) => Money::compare($g->balance(), '0') > 0)
            ->sortByDesc(fn ($g) => (float) $g->balance())
            ->values();

        $upcoming = $overview['goals']
            ->filter(fn ($g) => $g->status === SavingsGoal::ACTIVE && $g->target_date && ! $g->isTargetReached())
            ->sortBy(fn ($g) => $g->target_date->timestamp)
            ->take(5);

        return view('dashboard', [
            'overview' => $overview,
            'range' => $range,
            'rangeTotals' => $rangeTotals,
            'budget' => $summary->budgetStatus($user, now()),
            'recent' => $user->savingsTransactions()->whereNull('voided_at')->with('goal:id,name,icon,color')
                ->orderByDesc('transaction_date')->orderByDesc('id')->limit(6)->get(),
            'upcoming' => $upcoming,
            'topGoals' => $overview['goals']->where('status', SavingsGoal::ACTIVE)->sortByDesc(fn ($g) => $g->percent())->take(4),
            'chart' => [
                'labels' => array_column($series, 'label'),
                'contributed' => array_map('floatval', array_column($series, 'contributed')),
                'withdrawn' => array_map('floatval', array_column($series, 'withdrawn')),
                'expenses' => array_map('floatval', array_column($series, 'expenses')),
            ],
            'distribution' => [
                'labels' => $distribution->pluck('name')->all(),
                'values' => $distribution->map(fn ($g) => (float) $g->balance())->all(),
                'colors' => $distribution->pluck('color')->all(),
            ],
        ]);
    }
}
