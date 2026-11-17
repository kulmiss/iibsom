<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Rules\SomaliMobile;
use App\Support\Catalog;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * First sign-in ("welcome") asks: name, city, phone (+ WhatsApp), accept Terms.
 * The same details can be changed later on the profile page.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function welcome(Request $request): View|RedirectResponse
    {
        if ($this->authUser($request)->hasCompletedProfile()) {
            return redirect(lroute('account'));
        }

        return view('account.profile-form', ['welcome' => true, 'cities' => $this->catalog->cities(), 'user' => $request->user()]);
    }

    public function edit(Request $request): View
    {
        return view('account.profile-form', ['welcome' => false, 'cities' => $this->catalog->cities(), 'user' => $request->user()]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $welcome = ! $user->hasCompletedProfile();
        $cityIds = array_column($this->catalog->cities(), 'id');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'city_id' => ['required', 'integer', Rule::in($cityIds)],
            'phone' => ['required', 'string', 'max:25', new SomaliMobile],
            'phone_has_whatsapp' => ['sometimes', 'boolean'],
            'terms' => $welcome ? ['accepted'] : ['nullable'],
        ], [], [
            'name' => __('account.form.name'),
            'city_id' => __('account.form.city'),
            'phone' => __('account.form.phone'),
            'terms' => __('account.form.terms_short'),
        ]);

        if (BlockedPhone::isBlocked(SomaliPhone::normalize($data['phone']))) {
            throw ValidationException::withMessages(['phone' => __('account.validation.phone_blocked')]);
        }

        $user->fill([
            'name' => strip_tags(trim($data['name'])),
            'city_id' => (int) $data['city_id'],
            'phone' => SomaliPhone::normalize($data['phone']),
            'phone_has_whatsapp' => $request->boolean('phone_has_whatsapp'),
        ]);

        if ($welcome) {
            $user->forceFill(['terms_accepted_at' => now(), 'profile_completed_at' => now()]);
        }

        $user->save();

        if ($welcome) {
            return redirect()->intended(lroute('home'))->with('status', __('account.welcome.done'))
                ->with('show_tour', feature('site.welcome_tour'));
        }

        return redirect(lroute('account'))->with('status', __('account.profile.saved'));
    }
}
