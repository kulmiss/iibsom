<?php

namespace App\Http\Controllers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\CategoryField;
use App\Models\Report;
use App\Search\DatabaseAdSearch;
use App\Support\Ads\AdStats;
use App\Support\Ads\PriceHint;
use App\Support\Catalog;
use App\Support\Phone\SomaliPhone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdPageController extends Controller
{
    public function show(Request $request, string $category, string $city, string $slugId): View|RedirectResponse
    {
        $id = (int) substr(strrchr($slugId, '-') ?: '-0', 1);
        $ad = Ad::with(['category.parent', 'city', 'area', 'photos' => fn ($q) => $q->where('status', 'ready'), 'user'])->find($id);

        abort_unless($ad && $this->canSee($request, $ad), 404);

        // Old or wrong slug/category/city → one clean URL (good for Google).
        if ($ad->category->slug !== $category || $ad->city->slug !== $city || $ad->slug.'-'.$ad->id !== $slugId) {
            return redirect($ad->url(), 301);
        }

        $similar = feature('ads.similar') && $ad->isLive()
            ? DatabaseAdSearch::publicQuery()->withPhotoCount()->withSellerStars()->where('category_id', $ad->category_id)->whereKeyNot($ad->id)->latest('published_at')->limit(6)->get()
            : new EloquentCollection;

        // "More from this seller" (PR 76): the seller's other live ads, newest first.
        $fromSeller = feature('ads.more_from_seller') && $ad->isLive()
            ? DatabaseAdSearch::publicQuery()->withPhotoCount()->where('user_id', $ad->user_id)->whereKeyNot($ad->id)->latest('published_at')->limit(6)->get()
            : new EloquentCollection;

        // Both rows share one set of look-ups (category, city, cover photo) to keep the page light.
        (new EloquentCollection([...$similar->all(), ...$fromSeller->all()]))->load(['category', 'city', 'cover']);

        // Fewer than 6 others found = we already know the total; no extra count needed.
        $sellerLiveAds = feature('ads.more_from_seller') && $ad->isLive() && $fromSeller->count() < 6
            ? $fromSeller->count() + 1
            : DatabaseAdSearch::publicQuery()->where('user_id', $ad->user_id)->count();

        // The category's details, loaded once for the details list and the usual price.
        $defs = $ad->category->allFields()->keyBy('key');

        return view('ads.show', [
            'ad' => $ad,
            'similar' => $similar,
            'fromSeller' => $fromSeller,
            'market' => $this->marketPrice($ad, $defs),
            'discover' => $this->discoverMore($ad, $defs),
            'following' => feature('sellers.follow') && $request->user() !== null && $request->user()->following()->whereKey($ad->user_id)->exists(),
            'sellerLiveAds' => $sellerLiveAds,
            'fieldRows' => $this->fieldRows($ad, $defs),
            'reported' => $request->user() !== null && Report::where('ad_id', $ad->id)->where('user_id', $request->user()->id)->exists(),
            'saved' => feature('favourites') && $request->user() !== null && $request->user()->favourites()->whereKey($ad->id)->exists(),
        ]);
    }

    /**
     * The seller's number, only after a tap (stops bots that scan pages).
     * Rate-limited; nothing about the visitor is stored.
     */
    public function contact(Request $request, Ad $ad): JsonResponse
    {
        abort_unless($ad->isLive() && $ad->user->phone && ! $ad->user->isBanned(), 404);
        abort_unless(feature('contact.call') || feature('contact.whatsapp'), 404);

        // Stateless request (no session): the seller's own page says "mine" so their own tap isn't counted.
        if (! $request->boolean('mine')) {
            AdStats::record($ad, 'phone', $request);
        }

        $phone = $ad->user->phone;
        $text = rawurlencode(__('ads.page.whatsapp_text', ['title' => $ad->title, 'url' => $ad->url()]));

        return response()->json([
            'display' => SomaliPhone::format($phone),
            'call' => feature('contact.call') ? 'tel:'.$phone : null,
            'whatsapp' => feature('contact.whatsapp') && $ad->user->phone_has_whatsapp ? 'https://wa.me/'.SomaliPhone::whatsappNumber($phone).'?text='.$text : null,
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function report(Request $request, Ad $ad): RedirectResponse
    {
        abort_unless($ad->isLive(), 404);
        abort_if($ad->user_id === $this->authUser($request)->id, 403);

        $data = $request->validate([
            'reason' => ['required', Rule::in(Report::reasons())],
            'details' => ['nullable', 'string', 'max:500'],
            'website' => ['prohibited'], // honeypot: real people never fill this hidden field
        ]);

        $report = Report::firstOrNew(['ad_id' => $ad->id, 'user_id' => $this->authUser($request)->id]);
        $report->fill(['reason' => $data['reason'], 'details' => isset($data['details']) ? strip_tags($data['details']) : null]);
        $report->ad_id = $ad->id;
        $report->user_id = $this->authUser($request)->id;
        $report->status = 'waiting';
        $report->save();

        return redirect($ad->url().'#report')->with('status', __('ads.page.report_thanks'));
    }

    /**
     * Usual price of similar ads (PR 83, switch "ads.market_price"): the middle half of the prices of similar live or
     * sold ads (same category and condition; same brand and model when there are enough). Hidden with fewer than 5,
     * so no single seller's price can be worked out. This ad itself is left out.
     *
     * @param  Collection<string, CategoryField>  $defs
     * @return array{low: int, high: int, count: int, what: string, place: string}|null
     */
    private function marketPrice(Ad $ad, Collection $defs): ?array
    {
        if (! feature('ads.market_price') || ! $ad->isLive() || (int) $ad->price <= 0) {
            return null;
        }
        $hint = PriceHint::for($ad->category, $ad->condition, $ad->fields ?? [], $ad->id);
        if ($hint === null) {
            return null;
        }
        $brandField = $defs->get('brand');
        $brand = $brandField ? ($brandField->optionList()[(string) ($ad->fields['brand'] ?? '')] ?? '') : '';
        $what = match ($hint['scope']) {
            'brand_model' => trim($brand.' '.mb_substr((string) ($ad->fields['model'] ?? ''), 0, 40)),
            'brand' => $brand,
            default => $ad->category->label,
        };
        $price = (int) $ad->price;

        return [
            'low' => $hint['low'], 'high' => $hint['high'], 'count' => $hint['count'],
            'what' => $what !== '' ? $what : $ad->category->label,
            // Where this ad's price sits; "far below" is a gentle warning (very low prices are a common trick).
            'place' => match (true) {
                $price < $hint['low'] * 0.5 => 'far_below',
                $price < $hint['low'] => 'below',
                $price > $hint['high'] => 'above',
                default => 'within',
            },
        ];
    }

    /**
     * "Discover more" (PR 84, switch "ads.discover_more"): searches built from this ad's own details, so every
     * link has at least one ad (this one). No extra database queries: cities come from the cached catalog.
     *
     * @param  Collection<string, CategoryField>  $defs
     * @return list<array{label: string, url: string}>
     */
    private function discoverMore(Ad $ad, Collection $defs): array
    {
        if (! feature('ads.discover_more') || ! $ad->isLive()) {
            return [];
        }
        $category = $ad->category;
        $base = lroute('browse.category', ['category' => $category->slug]);
        $links = [];

        if (in_array($ad->condition, ['new', 'used'], true)) {
            $links[] = ['label' => __('ads.discover.condition', ['condition' => mb_strtolower($ad->condition === 'new' ? __('ads.form.new') : __('ads.form.used')), 'category' => $category->label]), 'url' => $base.'?condition='.$ad->condition];
        }

        $brandField = $defs->get('brand');
        $brand = is_scalar($ad->fields['brand'] ?? null) ? (string) $ad->fields['brand'] : '';
        if ($brandField && $brandField->type === 'select' && $brandField->filterable && $brand !== '' && isset($brandField->optionList()[$brand])) {
            $links[] = ['label' => __('ads.discover.brand', ['brand' => $brandField->optionList()[$brand], 'category' => $category->label]), 'url' => $base.'?'.http_build_query(['f' => ['brand' => $brand]])];
        }

        $cityLive = in_array($ad->city_id, array_column(app(Catalog::class)->cities(), 'id'), true);
        if ($cityLive) {
            $inCity = lroute('browse.category', ['category' => $category->slug, 'city' => $ad->city->slug]);
            $links[] = ['label' => __('ads.discover.in', ['category' => $category->label, 'place' => $ad->city->label]), 'url' => $inCity];
            if (feature('search.more_filters') && $ad->area && $ad->area->enabled && $ad->area->city_id === $ad->city_id) {
                $links[] = ['label' => __('ads.discover.in', ['category' => $category->label, 'place' => $ad->area->label]), 'url' => $inCity.'?area='.$ad->area->id];
            }
        }

        if ($category->parent) {
            $links[] = ['label' => __('ads.browse.all_in', ['category' => $category->parent->label]), 'url' => lroute('browse.category', ['category' => $category->parent->slug])];
        }

        return $links;
    }

    /** Live ads for everyone; the seller and staff can also preview their own waiting/rejected ad. */
    private function canSee(Request $request, Ad $ad): bool
    {
        if ($ad->is_demo && ! feature('site.demo_data')) {
            return false;
        }
        if ($ad->isLive() || $ad->status === AdStatus::Sold) {
            return $ad->category->isLive();
        }

        $user = $request->user();

        return $user !== null && ($user->id === $ad->user_id || $user->isStaff());
    }

    /** A small picture for each detail (PR 76); other fields get a plain tick. */
    private const FIELD_ICONS = [
        'brand' => 'badge-check', 'model' => 'car-front', 'year' => 'calendar', 'mileage' => 'gauge', 'fuel' => 'fuel',
        'transmission' => 'cog', 'colour' => 'palette', 'body_type' => 'car', 'seats' => 'armchair', 'part_type' => 'wrench',
        'bedrooms' => 'bed-double', 'bathrooms' => 'bath', 'furnished' => 'sofa', 'size' => 'ruler', 'ram' => 'memory-stick',
        'storage' => 'hard-drive', 'quantity' => 'boxes', 'job_type' => 'briefcase',
    ];

    /**
     * @param  Collection<string, CategoryField>  $defs
     * @return list<array{label: string, value: string, icon: string, url: ?string}>
     */
    private function fieldRows(Ad $ad, Collection $defs): array
    {
        $rows = [];
        foreach ($ad->fields ?? [] as $key => $value) {
            $f = $defs[$key] ?? null;
            if (! $f) {
                continue;
            }
            $shown = match ($f->type) {
                'select' => $f->optionList()[$value] ?? (string) $value,
                'boolean' => $value ? __('ads.form.yes') : __('ads.form.no'),
                'number' => number_format((int) $value),
                default => (string) $value,
            };
            if ($f->key === 'year') {
                $shown = (string) $value;
            }
            // Brand, model and other list choices that can be filtered link to all such ads (PR 82, "ads.field_links").
            $url = feature('ads.field_links') && $f->type === 'select' && $f->filterable && is_scalar($value) && $ad->isLive()
                ? lroute('browse.category', ['category' => $ad->category->slug]).'?'.http_build_query(['f' => [$f->key => (string) $value]])
                : null;
            $rows[] = ['label' => $f->label_text, 'value' => $shown.($f->unit ? ' '.$f->unit : ''), 'icon' => self::FIELD_ICONS[$f->key] ?? 'circle-check', 'url' => $url];
        }

        return $rows;
    }
}
