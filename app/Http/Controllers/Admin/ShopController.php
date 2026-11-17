<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shops: turn a shop page on for a number of months (monthly plan), or off. */
class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = now('Africa/Mogadishu')->toDateString();
        $filter = in_array($request->query('filter'), ['waiting', 'active', 'ended'], true) ? $request->query('filter') : 'waiting';
        $search = trim((string) $request->query('q', ''));

        $page = Shop::query()->with(['user:id,name,email,phone,verified_at,banned_at', 'city:id,name'])
            ->when($filter === 'waiting', fn ($q) => $q->whereNull('active_until'))
            ->when($filter === 'active', fn ($q) => $q->whereDate('active_until', '>=', $today))
            ->when($filter === 'ended', fn ($q) => $q->whereDate('active_until', '<', $today))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('slug', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('updated_at')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'url' => url('/shop/'.$s->slug),
                'logo' => $s->logoUrl(),
                'city' => $s->city?->label,
                'active_until' => $s->active_until?->toDateString(),
                'active' => $s->active_until !== null && $s->active_until->toDateString() >= $today && $s->user->banned_at === null,
                'live_ads' => $s->user->ads()->live()->count(),
                'owner' => ['id' => $s->user->id, 'name' => $s->user->name, 'email' => $s->user->email, 'phone' => $s->user->phone, 'banned' => $s->user->banned_at !== null],
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /** months = 1..12 adds that many months (from today, or from the current end if still active); 0 = turn off. */
    public function activate(Request $request, Shop $shop): JsonResponse
    {
        $data = $request->validate(['months' => ['required', 'integer', 'min:0', 'max:12']]);
        $months = (int) $data['months'];
        $today = now('Africa/Mogadishu')->startOfDay();

        if ($months === 0) {
            $shop->active_until = null;
            $summary = "Turned off shop “{$shop->name}”";
        } else {
            $from = $shop->active_until !== null && $shop->active_until->greaterThanOrEqualTo($today) ? $shop->active_until->copy() : $today;
            $until = $from->addMonthsNoOverflow($months);
            $shop->active_until = $until;
            $summary = "Shop “{$shop->name}” active until ".$until->toDateString()." (+{$months} month".($months > 1 ? 's' : '').')';
        }
        $shop->save();
        Activity::log('shop.activated', $summary, $shop->user);

        return response()->json(['active_until' => $shop->active_until?->toDateString()]);
    }
}
