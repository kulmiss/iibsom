<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Support\Admin\Activity;
use App\Support\Cache\PageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Staff check ID photos and give or remove the Verified badge. */
class VerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'expired'], true) ? $request->query('status') : 'pending';
        $page = VerificationRequest::query()->with(['user:id,name,email,phone,verified_at', 'reviewer:id,name'])
            ->where('status', $status)->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (VerificationRequest $v) => [
                'id' => $v->id,
                'status' => $v->status,
                'id_type' => $v->id_type,
                'has_photo' => $v->file_path !== null,
                'reason' => $v->reason,
                'created_at' => $v->created_at?->toIso8601String(),
                'reviewed_at' => $v->reviewed_at?->toIso8601String(),
                'reviewer' => $v->reviewer?->name,
                'user' => ['id' => $v->user->id, 'name' => $v->user->name, 'email' => $v->user->email, 'phone' => $v->user->phone, 'verified' => $v->user->verified_at !== null],
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
            'pending' => VerificationRequest::where('status', 'pending')->count(),
        ]);
    }

    /** The ID photo, decrypted in memory. Never cached, never indexed. */
    public function photo(VerificationRequest $verification): Response
    {
        $bytes = $verification->photoBytes();
        abort_if($bytes === null, 404);
        // Every look is in the Activity log: who (the staff member), when, whose ID (PR 107).
        Activity::log('verification.viewed', 'Looked at the ID photo of '.$verification->user->name, $verification->user, ['request' => $verification->id]);

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Disposition' => 'inline; filename="id.jpg"',
        ]);
    }

    public function approve(Request $request, VerificationRequest $verification): JsonResponse
    {
        abort_unless($verification->status === 'pending', 409, 'This request was already decided.');

        DB::transaction(function () use ($request, $verification) {
            $locked = VerificationRequest::whereKey($verification->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This request was already decided.');
            $locked->forceFill(['status' => 'approved', 'reviewed_by' => $this->authUser($request)->id, 'reviewed_at' => now()]);
            $locked->deletePhoto();
            $locked->save();
            $locked->user->forceFill(['verified_at' => now()])->save();
        });
        PageCache::bump(); // the badge shows on cached ad pages
        Activity::log('verification.approved', 'Gave the Verified badge to '.$verification->user->name, $verification->user);

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, VerificationRequest $verification): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        abort_unless($verification->status === 'pending', 409, 'This request was already decided.');

        DB::transaction(function () use ($request, $verification, $data) {
            $locked = VerificationRequest::whereKey($verification->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This request was already decided.');
            $locked->forceFill(['status' => 'rejected', 'reason' => strip_tags($data['reason']), 'reviewed_by' => $this->authUser($request)->id, 'reviewed_at' => now()]);
            $locked->deletePhoto();
            $locked->save();
        });
        Activity::log('verification.rejected', 'Did not give the Verified badge to '.$verification->user->name.': '.$data['reason'], $verification->user);

        return response()->json(['ok' => true]);
    }

    /** Take the badge away (for example after a scam report). */
    public function revoke(User $user): JsonResponse
    {
        abort_if($user->verified_at === null, 409, 'This user has no Verified badge.');
        $user->forceFill(['verified_at' => null])->save();
        PageCache::bump();
        Activity::log('verification.revoked', 'Removed the Verified badge of '.$user->name, $user);

        return response()->json(['ok' => true]);
    }
}
