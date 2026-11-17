<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the dashboard. Demo records are left out unless the
 * "Show demo data" switch is ON (then the screen says so).
 */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $demo = feature('site.demo_data');
        $now = CarbonImmutable::now();
        $ads = fn (): Builder => Ad::query()->when(! $demo, fn ($q) => $q->where('is_demo', false));
        $users = fn (): Builder => User::query()->where('role', User::CUSTOMER)->when(! $demo, fn ($q) => $q->where('is_demo', false));

        return response()->json([
            'includes_demo' => $demo,
            'cards' => [
                'ads_today' => $ads()->where('created_at', '>=', $now->startOfDay())->count(),
                'ads_week' => $ads()->where('created_at', '>=', $now->subDays(7))->count(),
                'ads_month' => $ads()->where('created_at', '>=', $now->subDays(30))->count(),
                // Without demo ads, the live-ad counters on cities already hold the answer (no scan of all ads).
                'ads_live' => $demo ? $ads()->where('status', AdStatus::Live)->count() : (int) City::sum('ads_count'),
                'ads_pending' => $ads()->where('status', AdStatus::Pending)->count(),
                'users_today' => $users()->where('created_at', '>=', $now->startOfDay())->count(),
                'users_week' => $users()->where('created_at', '>=', $now->subDays(7))->count(),
                'users_total' => $users()->count(),
                'reports_waiting' => Report::query()->where('status', 'waiting')->when(! $demo, fn ($q) => $q->where('is_demo', false))->count(),
            ],
            'chart' => $this->daily($ads(), $users(), 90),
            'top_categories' => $demo ? $this->topBy($ads(), 'categories', 'category_id')
                // The live-ad counters (kept up to date on every change, demo never counted): no scan of all ads.
                : Category::whereNotNull('parent_id')->where('ads_count', '>', 0)->orderByDesc('ads_count')->limit(6)->get(['name', 'ads_count'])
                    ->map(fn (Category $c) => ['name' => $c->label, 'total' => (int) $c->ads_count]),
            'top_cities' => $demo ? $this->topBy($ads(), 'cities', 'city_id')
                : City::where('ads_count', '>', 0)->orderByDesc('ads_count')->limit(6)->get(['name', 'ads_count'])
                    ->map(fn (City $c) => ['name' => $c->label, 'total' => (int) $c->ads_count]),
        ]);
    }

    /**
     * Live ads per category or city, counted (only while demo data is shown: the counters leave demo ads out).
     *
     * @param  Builder<Ad>  $ads
     * @return Collection<int, array{name: string, total: int}>
     */
    private function topBy(Builder $ads, string $table, string $column): Collection
    {
        return $ads->where('ads.status', AdStatus::Live)
            ->join($table, "{$table}.id", '=', "ads.{$column}")
            ->select("{$table}.name", DB::raw('count(*) as total'))
            ->groupBy("{$table}.id", "{$table}.name")->orderByDesc('total')->limit(6)->toBase()->get()
            ->map(fn ($row) => ['name' => translated(json_decode((string) $row->name, true)), 'total' => (int) $row->total]);
    }

    /**
     * @param  Builder<Ad>  $ads
     * @param  Builder<User>  $users
     * @return list<array{date:string, ads:int, users:int}>
     */
    private function daily(Builder $ads, Builder $users, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $adCounts = $ads->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $userCounts = $users->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');

        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $rows[] = ['date' => $d, 'ads' => (int) ($adCounts[$d] ?? 0), 'users' => (int) ($userCounts[$d] ?? 0)];
        }

        return $rows;
    }
}
