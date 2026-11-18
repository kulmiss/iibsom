<?php

namespace App\Http\Controllers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Report;
use App\Search\DatabaseAdSearch;
use App\Support\Ads\AdStats;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ? DatabaseAdSearch::publicQuery()->with(['category', 'city', 'cover'])->withPhotoCount()->where('category_id', $ad->category_id)->whereKeyNot($ad->id)->latest('published_at')->limit(6)->get()
            : collect();

        return view('ads.show', [
            'ad' => $ad,
            'similar' => $similar,
            'sellerLiveAds' => DatabaseAdSearch::publicQuery()->where('user_id', $ad->user_id)->count(),
            'fieldRows' => $this->fieldRows($ad),
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

    /** @return list<array{label: string, value: string}> */
    private function fieldRows(Ad $ad): array
    {
        $rows = [];
        $defs = $ad->category->allFields()->keyBy('key');
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
            $rows[] = ['label' => $f->label_text, 'value' => $shown.($f->unit ? ' '.$f->unit : '')];
        }

        return $rows;
    }
}
