<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Rules\SomaliMobile;
use App\Support\Admin\Activity;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedPhoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = BlockedPhone::with('blocker:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('phone', 'like', '%'.preg_replace('/\D/', '', $s).'%'))
            ->latest('id')->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (BlockedPhone $b) => [
                'id' => $b->id, 'phone' => $b->phone, 'reason' => $b->reason, 'by' => $b->blocker?->name, 'created_at' => $b->created_at?->toIso8601String(),
            ]),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', new SomaliMobile], 'reason' => ['nullable', 'string', 'max:200']]);
        $phone = SomaliPhone::normalize($data['phone']);

        $blocked = BlockedPhone::firstOrNew(['phone' => $phone]);
        $blocked->reason = isset($data['reason']) ? strip_tags($data['reason']) : null;
        $blocked->blocked_by = $this->authUser($request)->id;
        $blocked->save();
        Activity::log('phone.blocked', "Blocked phone {$phone}", $blocked);

        return response()->json(['id' => $blocked->id, 'phone' => $phone], 201);
    }

    public function destroy(BlockedPhone $blockedPhone): JsonResponse
    {
        $blockedPhone->delete();
        Activity::log('phone.unblocked', "Unblocked phone {$blockedPhone->phone}", $blockedPhone);

        return response()->json(['deleted' => true]);
    }
}
