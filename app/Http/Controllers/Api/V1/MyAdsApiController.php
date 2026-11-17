<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Http\Controllers\Ads\AdPhotoController;
use App\Http\Controllers\Ads\PostAdController;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Support\Ads\AdForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The write API (switch "api.write"): the signed-in seller's own ads, with the
 * SAME rules as the website (AdForm: checks, spam guard, daily limit, approval).
 * Auth: "Authorization: Bearer iib_..." (account page "App access").
 */
class MyAdsApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $this->authUser($request);

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'city_id' => $user->city_id,
            'verified' => feature('verified_badge') && $user->isVerified(),
            'member_since' => $user->created_at?->toDateString(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:500']]);
        $ads = Ad::where('user_id', $this->authUser($request)->id)
            ->with(['category', 'city', 'cover'])->latest('updated_at')->paginate(20);

        return response()->json([
            'data' => $ads->getCollection()->map(fn (Ad $ad) => $this->mine($request, $ad))->values(),
            'meta' => ['current_page' => $ads->currentPage(), 'last_page' => $ads->lastPage(), 'per_page' => $ads->perPage(), 'total' => $ads->total()],
        ]);
    }

    public function store(Request $request, AdForm $form, PostAdController $post): JsonResponse
    {
        $request->validate(['category' => ['required', 'string', 'max:80']]);
        $category = Category::where('slug', $request->string('category')->toString())->first();
        abort_if($category === null, 422, 'Unknown category.');
        $post->ensurePostable($category);
        $user = $this->authUser($request);

        AdForm::checkDailyLimit($user->id);
        $ad = $form->create($request, $category, $form->validate($request, $category));
        Log::info('ad.created', ['ad_id' => $ad->id, 'user_id' => $user->id, 'via' => 'api']);

        return response()->json(['data' => $this->mine($request, $ad->fresh() ?? $ad)], 201);
    }

    public function update(Request $request, int $id, AdForm $form): JsonResponse
    {
        $ad = $this->own($request, $id);
        abort_if($ad->status === AdStatus::Sold, 409, 'A sold ad cannot be changed.');

        $form->update($ad, $ad->category, $form->validate($request, $ad->category, $ad));
        Log::info('ad.edited', ['ad_id' => $ad->id, 'user_id' => $ad->user_id, 'via' => 'api']);

        return response()->json(['data' => $this->mine($request, $ad->fresh() ?? $ad)]);
    }

    public function sold(Request $request, int $id): JsonResponse
    {
        $ad = $this->own($request, $id);
        abort_unless($ad->status === AdStatus::Live, 409, 'Only a live ad can be marked as sold.');
        $ad->status = AdStatus::Sold;
        $ad->sold_at = now();
        $ad->save();

        return response()->json(['data' => $this->mine($request, $ad)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ad = $this->own($request, $id);
        $ad->delete(); // soft delete, same as the website
        Log::info('ad.deleted_by_owner', ['ad_id' => $ad->id, 'user_id' => $ad->user_id, 'via' => 'api']);

        return response()->json(['deleted' => true]);
    }

    /** Upload one photo; send its id in "photos" when you create or edit an ad. */
    public function photo(Request $request, AdPhotoController $photos): JsonResponse
    {
        return $photos->store($request);
    }

    /** A photo is "processing" for a few seconds after upload, then "ready". */
    public function photoStatus(Request $request, int $id): JsonResponse
    {
        $photo = AdPhoto::whereKey($id)->where('user_id', $this->authUser($request)->id)->first();
        abort_if($photo === null, 404);

        return response()->json(['id' => $photo->id, 'status' => $photo->status, 'thumb' => $photo->status === 'ready' ? $photo->url('thumb') : null]);
    }

    private function own(Request $request, int $id): Ad
    {
        $ad = Ad::whereKey($id)->where('user_id', $this->authUser($request)->id)->first();
        abort_if($ad === null, 404); // someone else's ad looks the same as no ad

        return $ad;
    }

    /** @return array<int|string, mixed> */
    private function mine(Request $request, Ad $ad): array
    {
        $ad->loadMissing(['category', 'city', 'cover']);

        return (new AdResource($ad))->toArray($request) + [
            'status' => $ad->status->value,
            'rejection_reason' => $ad->status === AdStatus::Rejected ? $ad->rejection_reason : null,
            'photo_ids' => $ad->photos()->orderBy('sort')->pluck('id')->all(),
        ];
    }
}
