<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdStatus;
use App\Http\Controllers\Account\PhoneConfirmController;
use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Models\Report;
use App\Models\User;
use App\Support\Admin\Activity;
use App\Support\Cache\PageCache;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Customers (staff are managed on the Staff screen). */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'last_login_at', 'ads_count'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $states = explode(',', (string) $request->state);

        $page = User::query()
            ->where('role', User::CUSTOMER)
            ->with('city:id,name')
            ->withCount('ads')
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($request->search, function ($q, $s) {
                $like = '%'.addcslashes($s, '%_').'%';
                $phone = SomaliPhone::normalize($s);
                $code = preg_match('/^(IIB-?)?([A-Z0-9]{5})$/i', trim($s), $m) ? 'IIB-'.strtoupper($m[2]) : null; // PR 108
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
                    ->when($code, fn ($q) => $q->orWhere('phone_confirm_code', $code))
                    ->when(ctype_digit($s), fn ($q) => $q->orWhere('id', (int) $s)));
            })
            ->when(in_array('banned', $states, true), fn ($q) => $q->whereNotNull('banned_at'))
            ->when(in_array('active', $states, true), fn ($q) => $q->whereNull('banned_at')->whereNotNull('profile_completed_at'))
            ->when(in_array('incomplete', $states, true), fn ($q) => $q->whereNull('profile_completed_at'))
            ->when(in_array('drugs', $states, true), fn ($q) => $q->where('flag', 'drugs')) // PR 73: drug words in chat
            ->orderBy($request->sort ?? 'created_at', $request->dir ?? 'desc')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => $this->row($u)),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        abort_if($user->isStaff(), 404);
        $user->loadCount('ads')->load('city');

        return response()->json($this->row($user) + [
            'ads_by_status' => $user->ads()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'reports_made' => $user->hasMany(Report::class)->count(),
            'phone_blocked' => BlockedPhone::isBlocked($user->phone),
        ]);
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        abort_if($user->isStaff(), 422, 'Staff accounts are changed on the Staff screen.');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'block_phone' => ['boolean'],
            'hide_ads' => ['boolean'],
        ]);
        $reason = strip_tags($data['reason']);

        DB::transaction(function () use ($user, $reason, $data, $request) {
            $user->forceFill(['banned_at' => now(), 'ban_reason' => $reason])->save();

            if ($data['hide_ads'] ?? true) {
                foreach ($user->ads()->whereIn('status', [AdStatus::Live, AdStatus::Pending])->get() as $ad) {
                    $ad->forceFill(['status' => AdStatus::Rejected, 'rejection_reason' => 'Account blocked', 'reviewed_by' => $this->authUser($request)->id, 'reviewed_at' => now()])->save();
                }
            }

            if (($data['block_phone'] ?? false) && $user->phone) {
                $blocked = BlockedPhone::firstOrNew(['phone' => $user->phone]);
                $blocked->reason = $reason;
                $blocked->blocked_by = $this->authUser($request)->id;
                $blocked->save();
            }
        });

        PageCache::bump(); // their shop page and seller details must not stay in the guest page cache
        Activity::log('user.banned', "Banned {$user->name} (#{$user->id}): {$reason}", $user, $data);

        return response()->json($this->row($user->refresh()->loadCount('ads')));
    }

    public function unban(User $user): JsonResponse
    {
        $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
        PageCache::bump();
        Activity::log('user.unbanned', "Unbanned {$user->name} (#{$user->id})", $user);

        return response()->json($this->row($user->refresh()->loadCount('ads')));
    }

    /** A staff member called or WhatsApp-ed the number and the seller answered: tick "Phone confirmed" (PR 108). */
    public function confirmPhone(Request $request, User $user): JsonResponse
    {
        abort_if($user->isStaff(), 404);
        abort_if(blank($user->phone), 422, 'This person has no phone number yet.');
        abort_if($user->phone_confirmed_at !== null, 409, 'This number is already confirmed.');

        $user->forceFill(['phone_confirmed_at' => now(), 'phone_confirmed_by' => $this->authUser($request)->id, 'phone_confirm_code' => null, 'phone_confirm_code_at' => null])->save();
        PageCache::bump(); // the tick shows on cached ad and seller pages
        Activity::log('user.phone_confirmed', "Confirmed the phone number of {$user->name} (#{$user->id})", $user);

        return response()->json($this->row($user->refresh()->loadCount('ads')));
    }

    public function unconfirmPhone(User $user): JsonResponse
    {
        abort_if($user->isStaff(), 404);
        abort_if($user->phone_confirmed_at === null, 409, 'This number is not confirmed.');

        $user->forceFill(['phone_confirmed_at' => null, 'phone_confirmed_by' => null])->save();
        PageCache::bump();
        Activity::log('user.phone_unconfirmed', "Removed \"Phone confirmed\" from {$user->name} (#{$user->id})", $user);

        return response()->json($this->row($user->refresh()->loadCount('ads')));
    }

    /** Staff looked at it: remove the drug mark (the person stays as they are). */
    public function clearFlag(User $user): JsonResponse
    {
        abort_if($user->isStaff(), 404);
        $user->forceFill(['flag' => null, 'flag_word' => null, 'flag_count' => 0, 'flagged_at' => null])->save();
        Activity::log('user.flag_cleared', "Cleared the drug mark of {$user->name} (#{$user->id})", $user);

        return response()->json($this->row($user->refresh()->loadCount('ads')));
    }

    /** @return array<string, mixed> */
    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'whatsapp' => $u->phone_has_whatsapp,
            'city' => $u->city?->label,
            'ads_count' => $u->ads_count ?? null,
            'profile_complete' => $u->hasCompletedProfile(),
            'banned' => $u->isBanned(),
            'ban_reason' => $u->ban_reason,
            'created_at' => $u->created_at?->toIso8601String(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'is_demo' => $u->is_demo,
            'verified' => $u->verified_at !== null && feature('verified_badge'),
            // "Phone confirmed" (PR 108): who ticked it and when; and a code the seller asked for (to match a WhatsApp message).
            'phone_confirmed' => feature('sellers.phone_confirmed') && $u->phone_confirmed_at !== null
                ? ['at' => $u->phone_confirmed_at->toIso8601String(), 'by' => User::whereKey($u->phone_confirmed_by)->value('name')]
                : null,
            'phone_code' => feature('sellers.phone_confirmed') && $u->phone_confirmed_at === null && $u->phone_confirm_code !== null
                && $u->phone_confirm_code_at?->gt(now()->subDays(PhoneConfirmController::CODE_DAYS))
                ? ['code' => $u->phone_confirm_code, 'at' => $u->phone_confirm_code_at->toIso8601String()]
                : null,
            'flag' => $u->flag === 'drugs'
                ? "Drug words in chat: “{$u->flag_word}” ({$u->flag_count}×, last ".($u->flagged_at?->format('j M Y H:i') ?? '?').')'
                : null,
        ];
    }
}
