<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\ScamAppeal;
use App\Models\ScamNumber;
use App\Models\User;
use App\Support\Admin\Activity;
use App\Support\Phone\SomaliPhone;
use App\Support\Safety\ScamNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Admin → Scam numbers (PR 109): staff mark numbers, see appeals and decide. Every step is logged. */
class ScamNumberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $removed = $request->query('status') === 'removed';
        $search = trim((string) $request->query('search', ''));
        $phone = SomaliPhone::normalize($search);

        $page = ScamNumber::query()->with('marker:id,name')
            ->withCount(['appeals as waiting_appeals' => fn ($q) => $q->where('status', 'waiting')])
            ->when($removed, fn ($q) => $q->whereNotNull('removed_at'), fn ($q) => $q->whereNull('removed_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('reason', 'like', '%'.addcslashes($search, '%_').'%')
                ->when($phone, fn ($q) => $q->orWhere('phone', $phone))))
            ->latest('id')->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ScamNumber $n) => $this->row($n))->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
            'waiting_appeals' => ScamAppeal::where('status', 'waiting')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'proof' => ['required', 'string', 'min:3', 'max:2000'],
        ], ['proof.required' => 'Write what proves it (report numbers, messages, payments). Numbers are never marked without proof.']);
        $phone = SomaliPhone::normalize($data['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'This is not a Somali mobile number (like 61 5123456).']);
        }

        $number = ScamNumber::firstOrNew(['phone' => $phone]);
        abort_if($number->exists && $number->removed_at === null, 409, 'This number is already on the list.');
        $number->forceFill([
            'reason' => strip_tags($data['reason']),
            'proof' => strip_tags($data['proof']),
            'marked_by' => $this->authUser($request)->id,
            'removed_at' => null, 'removed_by' => null, 'removed_reason' => null,
        ])->save();

        ScamNumbers::flush();
        $moved = ScamNumbers::sendAdsToReview($phone);
        Activity::log('scam.marked', "Marked {$phone} “Reported for scams”: {$number->reason}".($moved ? " ({$moved} live ads sent to review)" : ''), $number, ['ads_to_review' => $moved]);

        return response()->json($this->row($number->refresh()) + ['ads_to_review' => $moved], 201);
    }

    public function remove(Request $request, ScamNumber $number): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        abort_if($number->removed_at !== null, 409, 'This number is not on the list.');

        $this->takeOff($number, strip_tags($data['reason']), $this->authUser($request));
        Activity::log('scam.removed', "Took {$number->phone} off the scam list: {$number->removed_reason}", $number);

        return response()->json($this->row($number->refresh()));
    }

    public function appeals(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['waiting', 'accepted', 'refused'], true) ? (string) $request->query('status') : 'waiting';
        $page = ScamAppeal::query()->with('number:id,phone,reason,removed_at')
            ->where('status', $status)->orderBy($status === 'waiting' ? 'created_at' : 'decided_at', $status === 'waiting' ? 'asc' : 'desc')
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ScamAppeal $a) => [
                'id' => $a->id,
                'phone' => $a->number->phone,
                'listed_reason' => $a->number->reason,
                'still_listed' => $a->number->removed_at === null,
                'message' => $a->message,
                'contact' => $a->contact,
                'status' => $a->status,
                'decision_note' => $a->decision_note,
                'decided_by' => $a->decided_by ? User::whereKey($a->decided_by)->value('name') : null,
                'created_at' => $a->created_at?->toIso8601String(),
                'decided_at' => $a->decided_at?->toIso8601String(),
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /** Accept = the number comes off the list. Refuse = it stays. Both need a short note (logged). */
    public function decide(Request $request, ScamAppeal $appeal): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean'], 'note' => ['required', 'string', 'min:3', 'max:300']]);
        abort_if($appeal->status !== 'waiting', 409, 'This request was already decided.');
        $note = strip_tags($data['note']);
        $by = $this->authUser($request);

        DB::transaction(function () use ($appeal, $data, $note, $by) {
            $appeal->forceFill(['status' => $data['accept'] ? 'accepted' : 'refused', 'decision_note' => $note, 'decided_by' => $by->id, 'decided_at' => now()])->save();
            if ($data['accept'] && $appeal->number->removed_at === null) {
                $this->takeOff($appeal->number, 'Review accepted: '.$note, $by);
            }
        });

        $phone = $appeal->number->phone;
        Activity::log($data['accept'] ? 'scam.appeal_accepted' : 'scam.appeal_refused',
            ($data['accept'] ? 'Accepted' : 'Refused')." the review request for {$phone}: {$note}", $appeal->number);

        return response()->json(['ok' => true]);
    }

    private function takeOff(ScamNumber $number, string $reason, User $by): void
    {
        $number->forceFill(['removed_at' => now(), 'removed_by' => $by->id, 'removed_reason' => $reason])->save();
        ScamNumbers::flush();
    }

    /** @return array<string, mixed> */
    private function row(ScamNumber $n): array
    {
        return [
            'id' => $n->id,
            'phone' => $n->phone,
            'reason' => $n->reason,
            'proof' => $n->proof,
            'marked_by' => $n->marker?->name,
            'created_at' => $n->created_at?->toIso8601String(),
            'removed_at' => $n->removed_at?->toIso8601String(),
            'removed_reason' => $n->removed_reason,
            'waiting_appeals' => (int) ($n->waiting_appeals ?? $n->appeals()->where('status', 'waiting')->count()),
            'ads_waiting' => Ad::where('flag', ScamNumbers::FLAG)->where('status', AdStatus::Pending)
                ->whereIn('user_id', User::where('phone', $n->phone)->select('id'))->count(),
        ];
    }
}
