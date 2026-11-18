<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Notify\Inbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The notifications bell (switch "notifications"): the list and each person's choices. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $items = $user->inboxItems()->with(['ad:id,slug,status,category_id,city_id,expires_at,user_id', 'ad.category:id,slug', 'ad.city:id,slug'])->latest('id')->paginate(30);
        $unreadIds = $items->getCollection()->whereNull('read_at')->pluck('id');

        // Opening the list reads everything on it (like a phone's notification list).
        if ($unreadIds->isNotEmpty()) {
            $user->inboxItems()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return view('account.notifications.index', ['items' => $items, 'unreadIds' => $unreadIds->flip()]);
    }

    public function settings(Request $request): View
    {
        return view('account.notifications.settings', ['settings' => Inbox::settings($this->authUser($request))]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $request->validate([
            'on' => ['nullable', 'array', 'max:20'], 'on.*' => ['string', 'max:40'],
            'shown' => ['nullable', 'array', 'max:20'], 'shown.*' => ['string', 'max:40'],
        ]);
        $on = array_flip(array_map('strval', (array) $request->input('on', [])));
        $shown = array_flip(array_map('strval', (array) $request->input('shown', [])));
        // Only the choices that were on the page change (a choice hidden by a switch keeps its value).
        $settings = Inbox::settings($user);
        foreach (array_keys($settings) as $key) {
            if (isset($shown[$key])) {
                $settings[$key] = isset($on[$key]);
            }
        }
        $user->forceFill(['notify_settings' => $settings])->save();

        return redirect(lroute('notifications.settings'))->with('status', __('notify.settings.saved'));
    }
}
