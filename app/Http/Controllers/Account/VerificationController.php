<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/** Verified badge (Phase 2, switch "verified_badge"): the seller sends an ID photo. */
class VerificationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $this->authUser($request);

        return view('account.verify', [
            'verified' => $user->verified_at !== null,
            'latest' => $user->verificationRequests()->latest('id')->first(),
            'businessPrice' => (int) setting('verification.business_price'),
            'isBusiness' => $user->shop()->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if ($user->verified_at !== null || $user->verificationRequests()->where('status', 'pending')->exists()) {
            return redirect(lroute('verify'));
        }

        $data = $request->validate([
            'id_type' => ['required', Rule::in(VerificationRequest::ID_TYPES)],
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=250'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => __('account.verify.agree_required')], [
            'id_type' => __('account.verify.id_type'),
            'photo' => __('account.verify.photo'),
        ]);

        // Re-encode: removes hidden data (GPS, camera) and anything that is not a picture.
        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $jpeg = (string) $manager->read($request->file('photo')->getRealPath())->orient()->scaleDown(1600, 1600)->toJpeg(quality: 85);

        $verification = new VerificationRequest(['id_type' => $data['id_type'], 'file_path' => VerificationRequest::storePhoto($jpeg)]);
        $user->verificationRequests()->save($verification);

        return redirect(lroute('verify'))->with('status', __('account.verify.sent'));
    }
}
