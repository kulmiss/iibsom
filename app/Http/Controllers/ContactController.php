<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Support\Safety\GuestReports;
use App\Support\Safety\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Contact us" (switch "site.contact_form"). The page is the same for every guest (cached); sending is
 * stateless like guest reports: no session, no cookie, no IP kept. Protection: bot-trap field, signed time
 * stamp (too fast / too old = robot), rate limit by a short-lived keyed hash, a small sum under attack,
 * and at most 200 messages a day for the whole site. Robots get the same "thanks" as people.
 */
class ContactController extends Controller
{
    public const PER_DAY = 200;

    public function show(): View
    {
        return view('contact', ['token' => GuestReports::token(), 'old' => [], 'check' => false]);
    }

    /** No session here, so the form is shown again at once with what was typed and the problems. */
    private function again(Request $request, MessageBag $errors, bool $check = false): Response
    {
        $old = array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 2000) : '', $request->only('name', 'email', 'topic', 'message'));

        return response()->view('contact', [
            'token' => GuestReports::token(),
            'old' => $old,
            'check' => $check,
            'errors' => (new ViewErrorBag)->put('default', $errors),
        ])->header('Cache-Control', 'no-store, private'); // 200: browsers log a 422 page as an error
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $back = fn (string $state) => redirect(lroute('contact').'?sent='.$state);
        GuestReports::countTry();

        if (filled($request->input('website')) || ! GuestReports::tokenOk((string) $request->input('t', ''))) {
            return $back('thanks');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:120'],
            'topic' => ['required', Rule::in(ContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->again($request, $validator->errors());
        }
        if (GuestReports::underAttack() && ! GuestReports::sumOk((string) $request->input('t', ''), $request->input('sum'))) {
            return $this->again($request, new MessageBag(['sum' => __('site.contact.sum_wrong')]), true);
        }

        $data = $validator->validated();
        $message = trim(strip_tags(str_replace("\r\n", "\n", $data['message'])));
        if ($message === '' || SpamGuard::badWord($message) !== null) {
            return $back('thanks'); // quietly dropped, like robots
        }

        // A whole-site daily cap and "same text again" protection (the same message twice = one).
        $today = DB::table('contact_messages')->where('created_at', '>=', now()->startOfDay());
        if ((clone $today)->count() >= self::PER_DAY || (clone $today)->where('email', $data['email'])->where('message', $message)->exists()) {
            return $back('thanks');
        }

        ContactMessage::create([
            'name' => trim(strip_tags($data['name'])) ?: '-',
            'email' => mb_strtolower(trim($data['email'])),
            'topic' => $data['topic'],
            'message' => $message,
            'locale' => app()->getLocale(),
            'created_at' => now(),
        ]);

        return $back('thanks');
    }
}
