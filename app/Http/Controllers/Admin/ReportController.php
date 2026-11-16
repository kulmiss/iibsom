<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\Admin\Activity;
use App\Support\Ads\Moderation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $page = Report::query()
            ->with(['ad:id,title,status,user_id', 'ad.user:id,name', 'user:id,name', 'handler:id,name'])
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->reason, fn ($q, $r) => $q->whereIn('reason', explode(',', $r)))
            ->when($request->search, fn ($q, $s) => $q->whereHas('ad', fn ($q) => ctype_digit($s) ? $q->whereKey((int) $s) : $q->where('title', 'like', '%'.addcslashes($s, '%_').'%')))
            ->orderByRaw("status = 'waiting' desc")->latest('id')
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
            'action' => ['required', Rule::in(['dismiss', 'remove_ad'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $note = isset($data['note']) ? strip_tags($data['note']) : null;

        if ($data['action'] === 'dismiss') {
            $report->forceFill(['status' => 'rejected', 'handled_by' => $request->user()->id, 'handled_at' => now(), 'staff_note' => $note])->save();
            Activity::log('report.dismissed', "Dismissed report #{$report->id} on ad #{$report->ad_id}", $report);
        } else {
            $ad = $report->ad;
            if ($ad && ! $ad->trashed()) {
                Moderation::reject($ad, $request->user(), $note ?: __('reports.reason.'.$report->reason));
            }
            Report::where('ad_id', $report->ad_id)->where('status', 'waiting')
                ->update(['status' => 'handled', 'handled_by' => $request->user()->id, 'handled_at' => now(), 'staff_note' => $note]);
            Activity::log('report.ad_removed', "Removed ad #{$report->ad_id} after report #{$report->id}", $report);
        }

        return response()->json($this->row($report->fresh(['ad.user', 'user', 'handler'])));
    }

    private function row(Report $r): array
    {
        return [
            'id' => $r->id,
            'reason' => $r->reason,
            'reason_label' => __('reports.reason.'.$r->reason),
            'details' => $r->details,
            'status' => $r->status,
            'ad_id' => $r->ad_id,
            'ad_title' => $r->ad?->title,
            'ad_status' => $r->ad?->status?->value,
            'seller' => $r->ad?->user?->name,
            'reporter' => $r->user?->name,
            'handled_by' => $r->handler?->name,
            'staff_note' => $r->staff_note,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }
}
