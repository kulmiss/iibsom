<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\BlockedPhone;
use App\Models\Category;
use App\Models\User;
use App\Support\Catalog;
use App\Support\Notify\Inbox;
use App\Support\Safety\ScamNumbers;
use App\Support\Safety\SpamGuard;
use App\Support\Watchdog\Signals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The ad form rules and saving, shared by "Post an ad" and "Edit ad".
 */
class AdForm
{
    public function __construct(private readonly Catalog $catalog) {}

    /** @return array<string, mixed> clean, checked input */
    public function validate(Request $request, Category $category, ?Ad $editing = null): array
    {
        SpamGuard::honeypot($request);

        $cityIds = array_column($this->catalog->cities(), 'id');
        if ($editing) {
            $cityIds[] = $editing->city_id; // a city switched OFF later still works for its old ads
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:80'],
            'description' => ['required', 'string', 'min:20', 'max:3000'],
            'price' => ['nullable', 'integer', 'min:0', 'max:999999999', Rule::requiredIf(! $request->boolean('price_negotiable'))],
            'price_negotiable' => ['sometimes', 'boolean'],
            'condition' => $category->has_condition ? ['required', Rule::in(['new', 'used'])] : ['prohibited'],
            'city_id' => ['required', 'integer', Rule::in($cityIds)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('city_id', (int) $request->input('city_id'))->where('enabled', true)],
            'photos' => ['nullable', 'array', 'max:'.setting('ads.max_photos')],
            'photos.*' => ['integer', 'distinct'],
        ] + FieldRules::rules($category), [], [
            'title' => __('ads.form.title'),
            'description' => __('ads.form.description'),
            'price' => __('ads.form.price'),
            'condition' => __('ads.form.condition'),
            'city_id' => __('ads.form.city'),
            'area_id' => __('ads.form.area'),
        ] + FieldRules::names($category));

        $user = $request->user();
        abort_unless($user instanceof User, 401);
        if (BlockedPhone::isBlocked($user->phone)) {
            Signals::hit('banned_retry');
            throw ValidationException::withMessages(['title' => __('account.validation.phone_blocked')]);
        }

        $data['title'] = Str::squish(strip_tags($data['title']));
        $data['description'] = trim(strip_tags($data['description']));
        SpamGuard::checkAd(['title' => $data['title'], 'description' => $data['description']]);

        // Scam number list (PR 109): the seller's number, or a number written in the ad, was reported for scams.
        if (ScamNumbers::firstListed($user, $data['title'], $data['description']) !== null) {
            ScamNumbers::postTried($user);
            throw ValidationException::withMessages(['title' => __('ads.safety.scam_number')]);
        }

        $data['content_hash'] = hash('sha256', $user->id.'|'.Str::lower($data['title']).'|'.Str::lower(Str::squish($data['description'])));
        $duplicate = Ad::where('user_id', $user->id)->where('content_hash', $data['content_hash'])
            ->whereIn('status', [AdStatus::Pending, AdStatus::Live])
            ->when($editing !== null, fn ($q) => $q->whereKeyNot($editing?->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['title' => __('ads.form.duplicate')]);
        }

        $data['fields'] = FieldRules::clean($category, $request->input('fields', []));
        $data['price_negotiable'] = $request->boolean('price_negotiable');

        return $data;
    }

    /** Same limit for the website and the API: no more than N new ads in 24 hours. */
    public static function checkDailyLimit(int $userId): void
    {
        $today = Ad::withTrashed()->where('user_id', $userId)->where('created_at', '>=', now()->subDay())->count();

        if ($today >= setting('ads.max_per_day')) {
            throw ValidationException::withMessages(['title' => __('ads.form.daily_limit', ['count' => setting('ads.max_per_day')])]);
        }
    }

    /** Create a new ad (pending when approval is ON). */
    /** @param  array<string, mixed>  $data  from validate() */
    public function create(Request $request, Category $category, array $data): Ad
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ad = DB::transaction(function () use ($user, $category, $data) {
            $ad = new Ad(['category_id' => $category->id]);
            $ad->user_id = $user->id;
            $this->fill($ad, $category, $data);
            $ad->status = feature('ads.approval') ? AdStatus::Pending : AdStatus::Live;
            if ($ad->status === AdStatus::Live) {
                $this->publish($ad);
            }
            $ad->save();
            $this->syncPhotos($ad, $data['photos'] ?? []);

            return $ad;
        });

        // Approval OFF: the ad is live at once, so followers of the seller hear about it now (PR 67).
        Inbox::followedAd($ad);

        return $ad;
    }

    /**
     * Save changes. With approval ON the ad goes back to the queue (decision #9:
     * every ad is checked, edits too), so buyers never see unchecked text.
     */
    /** @param  array<string, mixed>  $data  from validate() */
    public function update(Ad $ad, Category $category, array $data): Ad
    {
        $oldPrice = $ad->status === AdStatus::Live ? $ad->getOriginal('price') : null;

        $ad = DB::transaction(function () use ($ad, $category, $data) {
            $this->fill($ad, $category, $data);

            if (feature('ads.approval')) {
                $ad->status = AdStatus::Pending;
                $ad->rejection_reason = null;
            } elseif ($ad->status !== AdStatus::Live && $ad->flag !== AutoHide::FLAG) {
                // (An ad hidden after reports stays hidden when its seller edits it: staff must check it, PR 89.)
                $ad->status = AdStatus::Live;
                $this->publish($ad);
            }
            $ad->save();
            $this->syncPhotos($ad, $data['photos'] ?? []);

            return $ad;
        });

        // Approval OFF: the new price is live at once, so people who saved the ad hear about a drop now
        // (with approval ON this happens when staff approve the change; see Moderation::approve).
        if ($oldPrice !== null && $ad->price !== null && $ad->isLive()) {
            Inbox::priceDrop($ad, (int) $oldPrice, (int) $ad->price);
        }

        return $ad;
    }

    public function publish(Ad $ad): void
    {
        $ad->published_at = now();
        $ad->expires_at = feature('ads.expiry') ? now()->addDays(setting('ads.expiry_days')) : null;
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Ad $ad, Category $category, array $data): void
    {
        $ad->fill([
            'city_id' => (int) $data['city_id'],
            'area_id' => $data['area_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => isset($data['price']) ? (int) $data['price'] : null,
            'price_negotiable' => $data['price_negotiable'],
            'condition' => $category->has_condition ? $data['condition'] : null,
            'fields' => $data['fields'],
        ]);
        $ad->slug = Str::limit(Str::slug($data['title']), 100, '') ?: 'ad';
        $ad->content_hash = $data['content_hash'];
    }

    /**
     * Photos in the order the seller chose. Only the seller's own new photos or
     * photos already on this ad are accepted; photos taken off are deleted.
     *
     * @param  list<int|string>  $ids
     */
    private function syncPhotos(Ad $ad, array $ids): void
    {
        $ids = array_map('intval', $ids);

        foreach ($ids as $sort => $photoId) {
            AdPhoto::whereKey($photoId)->where('user_id', $ad->user_id)
                ->where(fn ($q) => $q->whereNull('ad_id')->orWhere('ad_id', $ad->id))
                ->update(['ad_id' => $ad->id, 'sort' => $sort]);
        }

        AdPhoto::where('ad_id', $ad->id)->whereNotIn('id', $ids ?: [0])->get()
            ->each(fn (AdPhoto $photo) => self::deletePhoto($photo));
    }

    public static function deletePhoto(AdPhoto $photo): void
    {
        if ($photo->upload_path) {
            Storage::disk('local')->delete($photo->upload_path);
        }
        foreach ($photo->variants ?? [] as $formats) {
            Storage::disk($photo->disk)->delete(array_values($formats));
        }
        $photo->delete();
    }
}
