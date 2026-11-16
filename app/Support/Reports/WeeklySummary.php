<?php

namespace App\Support\Reports;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the weekly email to the owner. A week is Monday 00:00 to Monday
 * 00:00 in Mogadishu time. Demo data only counts while "Show demo data" is ON
 * (same as the admin dashboard).
 *
 * @phpstan-type Numbers array{new_ads: int, approved: int, rejected: int, new_users: int, reports: int}
 * @phpstan-type Summary array{from: CarbonImmutable, to: CarbonImmutable, week: Numbers, previous: Numbers, live_now: int, waiting_now: int, reports_waiting: int, top_categories: list<array{name: string, total: int}>, includes_demo: bool}
 */
final class WeeklySummary
{
    public const TIMEZONE = 'Africa/Mogadishu';

    /** The last full week before $now (Monday to Monday, Mogadishu time). */
    public static function lastWeek(?CarbonImmutable $now = null): CarbonImmutable
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);

        return $now->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
    }

    /** @return Summary */
    public static function build(CarbonImmutable $from): array
    {
        $to = $from->addWeek();
        $demo = feature('site.demo_data');

        return [
            'from' => $from,
            'to' => $to,
            'week' => self::numbers($from, $to, $demo),
            'previous' => self::numbers($from->subWeek(), $from, $demo),
            'live_now' => self::ads($demo)->live()->count(),
            'waiting_now' => self::ads($demo)->where('status', AdStatus::Pending)->count(),
            'reports_waiting' => Report::query()->when(! $demo, fn ($q) => $q->where('is_demo', false))->where('status', 'waiting')->count(),
            'top_categories' => self::topCategories($from, $to, $demo),
            'includes_demo' => $demo,
        ];
    }

    /** @return Numbers */
    private static function numbers(CarbonImmutable $from, CarbonImmutable $to, bool $demo): array
    {
        $between = [$from->utc(), $to->utc()];

        return [
            'new_ads' => self::ads($demo)->whereBetween('created_at', $between)->count(),
            // From the activity log: every approve/reject click counts once, even if the ad changed later.
            'approved' => self::actions('ad.approved', $between, $demo),
            'rejected' => self::actions('ad.rejected', $between, $demo),
            'new_users' => User::query()->where('role', User::CUSTOMER)->when(! $demo, fn ($q) => $q->where('is_demo', false))->whereBetween('created_at', $between)->count(),
            'reports' => Report::query()->when(! $demo, fn ($q) => $q->where('is_demo', false))->whereBetween('created_at', $between)->count(),
        ];
    }

    /** @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $between */
    private static function actions(string $action, array $between, bool $demo): int
    {
        return AdminActivity::query()->where('action', $action)->when(! $demo, fn ($q) => $q->where('is_demo', false))
            ->whereBetween('created_at', $between)->count();
    }

    /** @return list<array{name: string, total: int}> */
    private static function topCategories(CarbonImmutable $from, CarbonImmutable $to, bool $demo): array
    {
        $rows = self::ads($demo)->whereBetween('ads.created_at', [$from->utc(), $to->utc()])
            ->join('categories', 'categories.id', '=', 'ads.category_id')
            ->select('categories.name', DB::raw('count(*) as total'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('total')->limit(5)
            ->toBase()->get()->all();

        return array_values(array_map(fn (object $row) => [
            'name' => translated(json_decode((string) ($row->name ?? ''), true) ?: ''),
            'total' => (int) ($row->total ?? 0),
        ], $rows));
    }

    /** @return Builder<Ad> */
    private static function ads(bool $demo): Builder
    {
        return Ad::query()->when(! $demo, fn ($q) => $q->where('ads.is_demo', false));
    }
}
