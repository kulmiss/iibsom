<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\CategoryField;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Ads\AdSnapshot;
use App\Support\Ads\Moderation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use LogicException;

class AdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', Rule::in(['created_at', 'price', 'title'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $statuses = array_intersect(explode(',', (string) $request->status), array_column(AdStatus::cases(), 'value'));

        $page = Ad::query()
            ->with(['user:id,name,phone,banned_at', 'category:id,name,parent_id', 'city:id,name', 'photos' => fn ($q) => $q->where('status', 'ready')])
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($request->category, fn ($q, $c) => $q->whereIn('category_id', array_map('intval', explode(',', $c))))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q) => ctype_digit($s)
                    ? $q->whereKey((int) $s)
                    : $q->where('title', 'like', '%'.addcslashes($s, '%_').'%'));
            })
            ->orderBy($request->sort ?? 'created_at', $request->dir ?? ($request->sort ? 'asc' : 'desc'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (Ad $ad) => $this->row($ad)),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Ad $ad): JsonResponse
    {
        $ad->load(['user.city', 'category.parent', 'city', 'area', 'photos', 'reviewer:id,name']);
        $labels = $ad->category->allFields()->keyBy('key');

        return response()->json($this->row($ad) + [
            'description' => $ad->description,
            'condition' => $ad->condition,
            'area' => $ad->area?->label,
            'fields' => collect($ad->fields ?? [])->map(function ($value, $key) use ($labels) {
                /** @var CategoryField|null $f */
                $f = $labels[$key] ?? null;
                $shown = match (true) {
                    $f?->type === 'select' => $f->optionList()[$value] ?? $value,
                    $f?->type === 'boolean' => $value ? 'Yes' : 'No',
                    default => (string) $value,
                };

                return ['label' => $f !== null ? $f->label_text : $key, 'value' => $shown.($f?->unit ? " {$f->unit}" : '')];
            })->values(),
            'photos' => $ad->photos->map(fn ($p) => ['id' => $p->id, 'status' => $p->status, 'thumb' => $p->url('thumb'), 'large' => $p->url('large')]),
            'seller_info' => [
                'id' => $ad->user->id,
                'name' => $ad->user->name,
                'email' => $ad->user->email,
                'phone' => $ad->user->phone,
                'city' => $ad->user->city?->label,
                'banned' => $ad->user->isBanned(),
                'member_since' => $ad->user->created_at?->toDateString(),
                'ads_total' => $ad->user->ads()->count(),
            ],
            'reviewed_by' => $ad->reviewer?->name,
            'reviewed_at' => $ad->reviewed_at?->toIso8601String(),
            'published_at' => $ad->published_at?->toIso8601String(),
            'expires_at' => $ad->expires_at?->toIso8601String(),
            'changes' => AdSnapshot::changes($ad),
            'history' => AdminActivity::where('subject_type', 'ad')->where('subject_id', $ad->id)->latest('id')->limit(20)->with('user:id,name')->get()
                ->map(fn ($a) => ['summary' => $a->summary, 'by' => $a->user?->name, 'at' => $a->created_at->toIso8601String()]),
        ]);
    }

    public function approve(Request $request, Ad $ad): JsonResponse
    {
        try {
            Moderation::approve($ad, $this->authUser($request));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->row($ad->refresh()));
    }

    public function reject(Request $request, Ad $ad): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']])['reason'];
        Moderation::reject($ad, $this->authUser($request), strip_tags($reason));

        return response()->json($this->row($ad->refresh()));
    }

    /** Many ads at once: approve, reject (one reason for all) or delete. */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['approve', 'reject', 'delete'])],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'min:3', 'max:300'],
        ]);

        $needed = $data['action'] === 'delete' ? 'ads.delete' : 'ads.moderate';
        abort_unless($this->authUser($request)->hasPermission($needed), 403, 'You do not have permission to do this.');

        $done = 0;
        $skipped = 0;
        // Counters (live ads per category/city) are counted once at the end, not once per ad.
        AdCounters::deferred(function () use ($data, $request, &$done, &$skipped) {
            foreach (Ad::whereIn('id', $data['ids'])->get() as $ad) {
                try {
                    match ($data['action']) {
                        'approve' => Moderation::approve($ad, $this->authUser($request)),
                        'reject' => Moderation::reject($ad, $this->authUser($request), strip_tags($data['reason'])),
                        'delete' => $this->deleteAd($ad),
                        default => throw new LogicException('Unknown bulk action.'), // validation allows only the 3 above
                    };
                    $done++;
                } catch (InvalidArgumentException) {
                    $skipped++;
                }
            }
        });

        return response()->json(['done' => $done, 'skipped' => $skipped]);
    }

    public function update(Request $request, Ad $ad): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:80'],
            'description' => ['required', 'string', 'min:20', 'max:3000'],
            'price' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'price_negotiable' => ['boolean'],
        ]);

        $before = $ad->only(array_keys($data));
        $ad->fill([
            'title' => Str::squish(strip_tags($data['title'])),
            'description' => trim(strip_tags($data['description'])),
            'price' => $data['price'] ?? null,
            'price_negotiable' => (bool) ($data['price_negotiable'] ?? false),
        ]);
        // Staff edits of a live ad are the new approved version (not "edited by the seller").
        if ($ad->status === AdStatus::Live) {
            $ad->approved_snapshot = AdSnapshot::take($ad);
        }
        $ad->save();

        Activity::log('ad.edited', "Edited ad #{$ad->id} “{$ad->title}”", $ad, ['before' => $before]);

        return response()->json($this->row($ad->refresh()));
    }

    public function destroy(Ad $ad): JsonResponse
    {
        $this->deleteAd($ad);

        return response()->json(['deleted' => true]);
    }

    private function deleteAd(Ad $ad): void
    {
        $ad->delete(); // soft delete: data kept, hidden everywhere
        Activity::log('ad.deleted', "Deleted ad #{$ad->id} “{$ad->title}”", $ad);
    }

    /** @return array<string, mixed> */
    private function row(Ad $ad): array
    {
        $cover = $ad->relationLoaded('photos') ? $ad->photos->firstWhere('status', 'ready') : $ad->photos()->where('status', 'ready')->first();

        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'status' => $ad->status->value,
            'status_label' => $ad->status->label(),
            'rejection_reason' => $ad->rejection_reason,
            'closed_reason' => $ad->closed_reason ? __('ads.close.'.$ad->closed_reason, [], 'en') : null,
            'price' => $ad->priceLabel(),
            'category' => $ad->category->label,
            'category_id' => $ad->category_id,
            'city' => $ad->city->label,
            'seller' => $ad->user->name,
            'seller_id' => $ad->user_id,
            'seller_banned' => $ad->user->isBanned(),
            'thumb' => $cover?->url('thumb'),
            'photos_count' => $ad->relationLoaded('photos') ? $ad->photos->count() : null,
            'is_demo' => $ad->is_demo,
            'edited' => AdSnapshot::isEdited($ad),
            'created_at' => $ad->created_at?->toIso8601String(),
        ];
    }
}
