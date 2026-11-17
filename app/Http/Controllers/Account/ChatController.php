<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SellerReview;
use App\Models\User;
use App\Support\Safety\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * In-website chat (Phase 2, switch "chat"). Works on shared hosting: the open
 * conversation asks for new messages every few seconds (no live server needed),
 * and every page also works without JavaScript.
 */
class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $conversations = Conversation::query()->for($user)->whereNotNull('last_message_at')
            ->with(['ad:id,title,slug,category_id,city_id', 'ad.cover', 'buyer:id,name', 'seller:id,name'])
            ->addSelect(['last_body' => Message::select('body')->whereColumn('conversation_id', 'conversations.id')->latest('id')->limit(1)])
            ->latest('last_message_at')->paginate(30);

        return view('chat.index', ['conversations' => $conversations, 'user' => $user]);
    }

    /** "Chat with seller" on an ad page: open the existing conversation or start one. */
    public function start(Request $request, Ad $ad): RedirectResponse
    {
        $user = $this->authUser($request);
        abort_unless($ad->isLive() && ! $ad->user->isBanned(), 404);
        abort_if($ad->user_id === $user->id, 403);

        $conversation = Conversation::firstOrCreate(
            ['ad_id' => $ad->id, 'buyer_id' => $user->id],
            ['seller_id' => $ad->user_id],
        );

        // Quick question (switch "chat.quick_replies"): put the words in the box; the buyer still taps Send.
        $quick = (string) $request->input('quick');
        $params = ['conversation' => $conversation->id];
        if (feature('chat.quick_replies') && in_array($quick, self::QUICK, true)) {
            $params['say'] = $quick;
        }

        return redirect(lroute('chat.show', $params));
    }

    /** Quick questions a buyer can start a chat with (texts in lang/{code}/chat.php → quick). */
    public const QUICK = ['available', 'price', 'see', 'where'];

    public function show(Request $request, Conversation $conversation): View
    {
        $user = $this->authUser($request);
        abort_unless($conversation->has($user), 404);
        $conversation->load(['ad.cover', 'buyer', 'seller']);
        $conversation->markRead($user);

        return view('chat.show', [
            'conversation' => $conversation,
            'other' => $conversation->other($user),
            'messages' => $conversation->messages()->latest('id')->limit(100)->get()->reverse()->values(),
            'user' => $user,
            'draft' => feature('chat.quick_replies') && in_array($request->query('say'), self::QUICK, true)
                ? __('chat.quick.'.$request->query('say')) : '',
            // Seller ratings (Phase 3): only the buyer, only after the seller answered.
            'canRate' => feature('seller_ratings') && SellerReview::canRate($conversation, $user),
            'myReview' => feature('seller_ratings') && $conversation->buyer_id === $user->id
                ? SellerReview::where('conversation_id', $conversation->id)->first() : null,
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $user = $this->authUser($request);
        abort_unless($conversation->has($user), 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:'.Message::MAX_LENGTH]], [], ['body' => __('chat.message')]);
        $body = trim(str_replace("\r\n", "\n", $data['body']));

        $problem = match (true) {
            $body === '' => __('chat.empty'),
            $conversation->blocked_by !== null => __('chat.blocked'),
            $conversation->other($user)->isBanned() => __('chat.unavailable'),
            SpamGuard::badWord($body) !== null => __('chat.not_allowed'),
            default => null,
        };
        if ($problem !== null) {
            return $request->expectsJson()
                ? response()->json(['message' => $problem], 422)
                : back()->withInput()->withErrors(['body' => $problem]);
        }

        $message = DB::transaction(function () use ($conversation, $user, $body) {
            $message = $conversation->messages()->create(['sender_id' => $user->id, 'body' => $body]);
            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_id' => $message->id,
                $user->id === $conversation->buyer_id ? 'buyer_read_id' : 'seller_read_id' => $message->id,
            ])->save();

            return $message;
        });

        return $request->expectsJson()
            ? response()->json(['message' => $this->row($message, $user)], 201)
            : redirect(lroute('chat.show', ['conversation' => $conversation->id]).'#m'.$message->id);
    }

    /** New messages after a given id (the page asks every few seconds while it is open). */
    public function since(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->authUser($request);
        abort_unless($conversation->has($user), 404);
        $after = max(0, (int) $request->query('after', '0'));

        $messages = $conversation->messages()->where('id', '>', $after)->orderBy('id')->limit(50)->get();
        if ($messages->isNotEmpty()) {
            $conversation->markRead($user);
        }

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => $this->row($m, $user))->values(),
            'blocked' => $conversation->blocked_by !== null,
        ])->header('Cache-Control', 'no-store');
    }

    /** Either side can block: nobody can send more messages in this conversation. Only the blocker can undo it. */
    public function block(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $this->authUser($request);
        abort_unless($conversation->has($user), 404);

        if ($conversation->blocked_by === null) {
            $conversation->forceFill(['blocked_by' => $user->id])->save();
        } elseif ($conversation->blocked_by === $user->id) {
            $conversation->forceFill(['blocked_by' => null])->save();
        }

        return redirect(lroute('chat.show', ['conversation' => $conversation->id]));
    }

    /** @return array{id: int, mine: bool, body: string, time: string} */
    private function row(Message $m, User $user): array
    {
        return ['id' => $m->id, 'mine' => $m->sender_id === $user->id, 'body' => $m->body, 'time' => $m->clock()];
    }
}
