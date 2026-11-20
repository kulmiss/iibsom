<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\Admin\Activity;
use App\Support\Ads\AutoHide;
use App\Support\Ads\Moderation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Report::query()->select('reports.*')
            ->with(['ad:id,title,status,user_id,flag', 'ad.user:id,name', 'user:id,name', 'handler:id,name'])
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->reason, fn ($q, $r) => $q->whereIn('reason', explode(',', $r)))
            ->when($request->search, fn ($q, $s) => $q->whereHas('ad', fn ($q) => ctype_digit($s) ? $q->whereKey((int) $s) : $q->where('title', 'like', '%'.addcslashes($s, '%_').'%')))
            // Waiting first; then the ads with the most waiting reports (guest repeats count) come to the top.
            ->addSelect(['ad_reports' => Report::from('reports as r2')->selectRaw('COALESCE(SUM(r2.times), 0)')
                ->whereColumn('r2.ad_id', 'reports.ad_id')->where('r2.status', 'waiting')])
            ->orderByRaw("status = 'waiting' desc")->orderByDesc('ad_reports')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (Report $r) => $this->row($r)),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * dismiss = report was wrong (status "rejected");
     * remove_ad = the ad is rejected with the report reason, and all waiting reports on it are handled.
     */
    public function handle(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'remove_ad', 'restore_ad'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $note = isset($data['note']) ? strip_tags($data['note']) : null;

        if ($data['action'] === 'restore_ad') {
            // PR 89: an ad hidden after reports is fine: it goes live again and its waiting reports are closed.
            $ad = $report->ad;
            abort_unless($ad->status === AdStatus::Pending && $ad->flag === AutoHide::FLAG && ! $ad->trashed(), 422, 'Only an ad that was hidden after reports can be put back here.');
            try {
                Moderation::approve($ad, $this->authUser($request));
            } catch (InvalidArgumentException $e) { // e.g. its number is on the scam list (PR 109)
                return response()->json(['message' => $e->getMessage()], 422);
            }
            if ($note !== null) {
                Report::where('ad_id', $ad->id)->where('handled_at', '>=', now()->subMinute())->where('status', 'rejected')->update(['staff_note' => $note]);
            }
            Activity::log('report.ad_restored', "Put back ad #{$ad->id} after checking the reports", $report);
        } elseif ($data['action'] === 'dismiss') {
            $report->forceFill(['status' => 'rejected', 'handled_by' => $this->authUser($request)->id, 'handled_at' => now(), 'staff_note' => $note])->save();
            Activity::log('report.dismissed', "Dismissed report #{$report->id} on ad #{$report->ad_id}", $report);
        } else {
            $ad = $report->ad;
            if (! $ad->trashed()) {
                Moderation::reject($ad, $this->authUser($request), $note ?: __('reports.reason.'.$report->reason));
            }
            Report::where('ad_id', $report->ad_id)->where('status', 'waiting')
                ->update(['status' => 'handled', 'handled_by' => $this->authUser($request)->id, 'handled_at' => now(), 'staff_note' => $note]);
            Activity::log('report.ad_removed', "Removed ad #{$report->ad_id} after report #{$report->id}", $report);
        }

        return response()->json($this->row($report->refresh()->load(['ad.user', 'user', 'handler'])));
    }

    /** @return array<string, mixed> */
    private function row(Report $r): array
    {
        return [
            'id' => $r->id,
            'reason' => $r->reason,
            'reason_label' => __('reports.reason.'.$r->reason),
            'details' => $r->details,
            'status' => $r->status,
            'ad_id' => $r->ad_id,
            'ad_title' => $r->ad->title,
            'ad_status' => $r->ad->status->value,
            'ad_hidden' => $r->ad->status === AdStatus::Pending && $r->ad->flag === AutoHide::FLAG,
            'seller' => $r->ad->user->name,
            'reporter' => $r->user !== null ? $r->user->name : 'Guest',
            'is_guest' => $r->user_id === null,
            'contact' => $r->contact,
            'times' => (int) $r->times,
            'ad_reports' => (int) ($r->getAttribute('ad_reports') ?? 0),
            'handled_by' => $r->handler?->name,
            'staff_note' => $r->staff_note,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
