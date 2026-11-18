<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\CallBackMail;
use App\Models\Ad;
use App\Models\InboxItem;
use App\Support\Notify\Inbox;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Ask the seller to call me" (switch "contact.call_back", needs "notifications").
 * The buyer chooses to share their own phone number with this one seller: it goes to the seller's
 * bell (and email, if the seller wants). Once per ad per day, 10 a day per buyer.
 */
class CallBackController extends Controller
{
    public const PER_DAY = 10;

    public function store(Request $request, Ad $ad): RedirectResponse
    {
        $buyer = $this->authUser($request);
        $seller = $ad->user;
        abort_unless($ad->isLive() && ! $seller->isBanned(), 404);
        abort_if($seller->id === $buyer->id, 403);
        abort_if(blank($buyer->phone), 403); // a finished profile always has one

        $back = redirect($ad->url()); // the message shows at the top of the page
        $mine = InboxItem::where('kind', 'call_back')->where('created_at', '>=', now()->subDay())->where('data->buyer', $buyer->id);
        if ((clone $mine)->where('ad_id', $ad->id)->exists()) {
            return $back->with('status', __('notify.call_back.already'));
        }
        if ((clone $mine)->count() >= self::PER_DAY) {
            return $back->with('status', __('notify.call_back.too_many'));
        }

        $phone = SomaliPhone::format((string) $buyer->phone);
        $data = ['buyer' => $buyer->id, 'name' => $buyer->name, 'phone' => $phone, 'title' => $ad->title];
        // Always kept for the seller (their bell may be OFF: the email or the next visit still has it).
        InboxItem::create(['user_id' => $seller->id, 'kind' => 'call_back', 'ad_id' => $ad->id, 'data' => $data, 'created_at' => now(),
            'read_at' => Inbox::wants($seller, 'call_back') ? null : now()]);

        if ($seller->email && Inbox::wants($seller, 'call_back', 'email')) {
            try {
                Mail::to($seller->email)->send(new CallBackMail($ad, $buyer->name, $phone));
            } catch (Throwable $e) {
                Log::error('call_back.mail_failed', ['ad' => $ad->id, 'error' => $e->getMessage()]); // the bell still has it
            }
        }

        return $back->with('status', __('notify.call_back.sent', ['phone' => $phone]));
    }
}
