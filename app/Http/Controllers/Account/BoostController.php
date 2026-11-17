<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Payment;
use App\Support\Files\PrivateFiles;
use App\Support\Money\Boosts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/** Paid boosts (Phase 2, switch "boosts"): pay by mobile money, upload the receipt, staff approve. */
class BoostController extends Controller
{
    /**
     * Ways to pay right now: in TEST MODE all of them (with a test number),
     * otherwise only those with a merchant number in Settings.
     *
     * @return array<string, array{label: string, number: string}>
     */
    public static function methods(): array
    {
        $test = (bool) setting('payments.test_mode');
        $numbers = (array) setting('payments.numbers');
        $out = [];
        foreach (config()->array('boosts.methods') as $key => $label) {
            $number = trim((string) ($numbers[$key] ?? ''));
            if ($number !== '' || $test) {
                $out[$key] = ['label' => (string) $label, 'number' => $number !== '' ? $number : '000 000 000'];
            }
        }

        return $out;
    }

    public function show(Request $request, Ad $ad): View
    {
        $this->own($request, $ad);

        return view('account.boost', [
            'ad' => $ad,
            'packages' => config()->array('boosts.packages'),
            'methods' => self::methods(),
            'testMode' => (bool) setting('payments.test_mode'),
            'pending' => Payment::where('ad_id', $ad->id)->where('status', 'pending')->exists(),
        ]);
    }

    public function store(Request $request, Ad $ad): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless($ad->isLive(), 404);
        if (Payment::where('ad_id', $ad->id)->where('status', 'pending')->exists()) {
            return redirect(lroute('account.boost', ['ad' => $ad->id]));
        }

        $data = $request->validate([
            'package' => ['required', Rule::in(array_keys(config()->array('boosts.packages')))],
            'method' => ['required', Rule::in(array_keys(self::methods()))],
            'reference' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'receipt' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ], [
            'reference.regex' => __('ads.boost.reference_rule'),
        ], [
            'package' => __('ads.boost.package'),
            'method' => __('ads.boost.method'),
            'reference' => __('ads.boost.reference'),
            'receipt' => __('ads.boost.receipt'),
        ]);

        $reference = strtoupper($data['reference']);
        if (Payment::where('method', $data['method'])->where('reference', $reference)->exists()) {
            return back()->withInput()->withErrors(['reference' => __('ads.boost.reference_used')]);
        }

        // Re-encode the screenshot (removes hidden data), then keep it encrypted and private.
        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $jpeg = (string) $manager->read($request->file('receipt')->getRealPath())->orient()->scaleDown(1600, 1600)->toJpeg(quality: 85);

        $payment = new Payment([
            'ad_id' => $ad->id,
            'purpose' => 'boost',
            'package' => $data['package'],
            'method' => $data['method'],
            'amount_cents' => Boosts::package($data['package'])['price_cents'], // price from the server, never from the form
            'currency' => 'USD',
            'reference' => $reference,
            'receipt_path' => PrivateFiles::put('receipts', $jpeg),
            'is_test' => (bool) setting('payments.test_mode'),
        ]);
        try {
            $this->authUser($request)->payments()->save($payment);
        } catch (UniqueConstraintViolationException) {
            // The same transaction ID sent twice at the same moment: the database refuses the second.
            PrivateFiles::delete($payment->receipt_path);

            return back()->withInput()->withErrors(['reference' => __('ads.boost.reference_used')]);
        }

        return redirect(lroute('account.payments'))->with('status', __('ads.boost.sent'));
    }

    public function payments(Request $request): View
    {
        return view('account.payments', [
            'payments' => $this->authUser($request)->payments()->with('ad:id,title')->latest('id')->paginate(20),
        ]);
    }

    private function own(Request $request, Ad $ad): void
    {
        abort_unless($ad->user_id === $this->authUser($request)->id, 404);
    }
}
