<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AiRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AI usage and cost.
 *
 * The free product only works if this stays small. Vol 1's whole economic argument is that
 * we monetise the 95% who will never pay, and that only holds while serving them costs
 * almost nothing — the target is ₹0.40 per active user per month.
 *
 * This page exists so that number is watched rather than discovered in a monthly bill.
 * Every figure carries the threshold it is judged against, because a cost with no
 * comparison is just a number nobody acts on.
 *
 * Owner and content lead only: a content editor has no reason to see spend, and the
 * permission boundary is why AI is its own navigation group.
 */
class AiCost extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-rupee';

    protected static ?string $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'Usage & cost';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.ai-cost';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $since = now()->startOfMonth();

        $byFeature = AiRequest::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('feature,
                count(*) as requests,
                sum(cost_paise) as cost_paise,
                sum(case when cache_hit = 1 then 1 else 0 end) as cache_hits,
                sum(case when refused_reason is not null then 1 else 0 end) as refusals')
            ->groupBy('feature')
            ->orderByDesc('cost_paise')
            ->get();

        $totalPaise = (int) $byFeature->sum('cost_paise');

        // Active users, not registered ones. Dividing by everyone who ever signed up
        // flatters the number and hides the trend that matters.
        $activeUsers = max(
            User::query()->where('last_active_at', '>=', $since)->count(),
            1,
        );

        return [
            'byFeature' => $byFeature,
            'totalPaise' => $totalPaise,
            'perUserPaise' => (int) round($totalPaise / $activeUsers),
            'budgetPaise' => (int) config('ai.cost.budget_paise_per_active_user_per_month', 40),
            'activeUsers' => $activeUsers,
            'cacheHitRate' => $this->cacheHitRate($byFeature),
            'refusals' => $this->refusals($since),
        ];
    }

    /**
     * Caching by content rather than by user is the single decision that makes the AI
     * layer affordable, so the hit rate is the number to watch when cost drifts up.
     *
     * @param  Collection<int, object>  $byFeature
     */
    private function cacheHitRate($byFeature): int
    {
        $requests = (int) $byFeature->sum('requests');

        if ($requests === 0) {
            return 0;
        }

        return (int) round($byFeature->sum('cache_hits') / $requests * 100);
    }

    /**
     * Refusals, broken down.
     *
     * These are shown as a HEALTH metric, not a failure count. When a thousand people ask
     * what the cutoff will be and every one gets real historical data instead of a guess,
     * that number is the product working exactly as designed.
     *
     * @return Collection<int, object>
     */
    private function refusals(CarbonInterface $since)
    {
        return DB::table('ai_requests')
            ->where('created_at', '>=', $since)
            ->whereNotNull('refused_reason')
            ->selectRaw('refused_reason, count(*) as total')
            ->groupBy('refused_reason')
            ->orderByDesc('total')
            ->get();
    }
}
