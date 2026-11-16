<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAdPhoto;
use App\Models\AdPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos are uploaded one by one while the seller fills in the form
 * (so a slow connection doesn't lose everything at "Submit").
 */
class AdPhotoController extends Controller
{
    /** Max photos waiting to be attached to an ad, per user (stops abuse). */
    private const MAX_UNATTACHED = 24;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Real image check (not just the file name): mimes uses the file content.
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:12288', 'dimensions:min_width=200,min_height=200,max_width=12000,max_height=12000'],
        ]);

        $user = $request->user();
        abort_if(AdPhoto::where('user_id', $user->id)->whereNull('ad_id')->count() >= self::MAX_UNATTACHED, 429, __('ads.photos.too_many'));

        $file = $request->file('photo');
        $path = $file->storeAs('uploads/'.$user->id, Str::uuid().'.'.$file->extension(), 'local');

        $photo = AdPhoto::create([
            'user_id' => $user->id,
            'upload_path' => $path,
            'status' => 'processing',
        ]);

        ProcessAdPhoto::dispatch($photo->id);

        return response()->json(['id' => $photo->id, 'status' => $photo->fresh()->status], 201);
    }

    public function destroy(Request $request, AdPhoto $photo): JsonResponse
    {
        // Only your own photo, and only while it's not part of an ad yet.
        abort_unless($photo->user_id === $request->user()->id && $photo->ad_id === null, 404);

        if ($photo->upload_path) {
            Storage::disk('local')->delete($photo->upload_path);
        }
        foreach ($photo->variants ?? [] as $formats) {
            Storage::disk($photo->disk)->delete(array_values($formats));
        }
        $photo->delete();

        return response()->json(['deleted' => true]);
    }
}
