<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Search\DatabaseAdSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public seller page (switch "sellers.page"): every seller's live ads in one place.
 * Only what the ad page already shows (name, city, member since, badges). Not in Google (noindex),
 * because most sellers are private people; business shops have their own indexed page.
 */
class SellerPageController extends Controller
{
    public function show(int $user): View|RedirectResponse
    {
        $seller = User::with('city')->find($user);
        abort_if($seller === null || $seller->isBanned() || $seller->role !== User::CUSTOMER || ! $seller->profile_completed_at, 404);
        abort_if($seller->is_demo && ! feature('site.demo_data'), 404);

        if (feature('shops') && $seller->shop?->isActive()) {
            return redirect($seller->shop->url(), 301); // a business: its shop page has everything
        }

        // Filters on the seller's ads (PR 79, switch "sellers.page_plus"): one category, and sort.
        $plus = feature('sellers.page_plus');
        $facets = [];
        $category = null;
        $sort = 'newest';
        if ($plus) {
            $data = request()->validate(['c' => ['nullable', 'string', 'max:80'], 'sort' => ['nullable', 'in:newest,cheapest,expensive']]);
            $sort = $data['sort'] ?? 'newest';
            $rows = DatabaseAdSearch::publicQuery()->where('ads.user_id', $seller->id)->groupBy('category_id')
                ->toBase()->selectRaw('category_id, COUNT(*) as n')->pluck('n', 'category_id');
            if (count($rows) > 1) {
                $cats = Category::whereIn('id', $rows->keys())->get(['id', 'slug', 'icon', 'name']);
                foreach ($cats as $cat) {
                    $facets[] = ['id' => $cat->id, 'slug' => $cat->slug, 'icon' => $cat->icon, 'label' => $cat->label, 'count' => (int) $rows[$cat->id]];
                }
                usort($facets, fn (array $a, array $b) => $b['count'] <=> $a['count']);
            }
            $category = isset($data['c']) ? collect($facets)->firstWhere('slug', $data['c']) : null;
        }

        $ads = DatabaseAdSearch::publicQuery()->where('ads.user_id', $seller->id)
            ->when($category, fn ($q) => $q->where('category_id', $category['id'] ?? 0))
            ->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()
            ->when($sort === 'cheapest', fn ($q) => $q->orderByRaw('price IS NULL')->orderBy('price'))
            ->when($sort === 'expensive', fn ($q) => $q->orderByDesc('price'))
            ->latest('published_at')->paginate(24)->withQueryString();
        abort_if($ads->total() === 0 && $ads->currentPage() > 1, 404);

        $user = auth()->user();
        $following = $user !== null && feature('sellers.follow') && $user->following()->whereKey($seller->id)->exists();
        $followers = feature('sellers.follow') ? (int) DB::table('follows')->where('seller_id', $seller->id)->count() : 0;

        $total = $category !== null ? array_sum(array_column($facets, 'count')) : $ads->total();

        return view('sellers.show', compact('seller', 'ads', 'following', 'followers', 'facets', 'category', 'sort', 'total'));
    }
}
