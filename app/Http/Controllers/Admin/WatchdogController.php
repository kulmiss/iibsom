<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WatchdogProblem;
use App\Support\Admin\Activity;
use App\Support\Watchdog\Messages;
use App\Support\Watchdog\Notifier;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use App\Support\Watchdog\WatchdogSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Watchdog (PR 96): status, open problems, "please check", history, settings and test alerts. */
class WatchdogController extends Controller
{
    private const AREAS = ['health' => 'Site health', 'security' => 'Security', 'ads' => 'Ad safety', 'backups' => 'Backups'];

    public function __construct(private readonly WatchdogSettings $settings, private readonly Watchdog $watchdog) {}

    public function index(): JsonResponse
    {
        $open = WatchdogProblem::open()->get();
        $pleaseCheck = $open->filter(fn (WatchdogProblem $p) => $p->level === 'info' && $p->subject_id !== null);
        $problems = $open->diff($pleaseCheck)
            ->sortByDesc(fn (WatchdogProblem $p) => WatchdogProblem::LEVELS[$p->level] * 1e10 + $p->last_seen_at->getTimestamp())->values();

        $checked = State::read();
        $checks = config('watchdog.checks');
        $cards = [];
        foreach (self::AREAS as $area => $label) {
            $mine = $problems->where('area', $area);
            $times = [];
            foreach ($checks as $key => $check) {
                if ($check['area'] === $area && isset($checked["checked.{$key}"])) {
                    $times[] = (int) $checked["checked.{$key}"];
                }
            }
            $cards[] = [
                'area' => $area,
                'label' => $label,
                'status' => self::worst($mine),
                'urgent' => $mine->where('level', 'urgent')->count(),
                'warning' => $mine->where('level', 'warning')->count(),
                'info' => $mine->where('level', 'info')->count(),
                'last_check' => $times === [] ? null : now()->setTimestamp(max($times))->toIso8601String(),
            ];
        }
        $lastRun = $checked['last_run'] ?? null;

        return response()->json([
            'status' => self::worst($problems),
            'last_run' => is_int($lastRun) ? now()->setTimestamp($lastRun)->toIso8601String() : null,
            'cards' => $cards,
            'problems' => $problems->map(fn (WatchdogProblem $p) => $this->problem($p))->all(),
            'please_check' => $pleaseCheck->sortByDesc('first_seen_at')->values()->map(fn (WatchdogProblem $p) => $this->problem($p))->all(),
            'channels' => self::channelSummary($this->settings->channels()),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['nullable', Rule::in(array_keys(WatchdogProblem::LEVELS))],
            'area' => ['nullable', Rule::in(array_keys(self::AREAS))],
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = WatchdogProblem::with(['fixer:id,name', 'muter:id,name'])
            ->when($data['level'] ?? null, fn ($q, $level) => $q->where('level', $level))
            ->when($data['area'] ?? null, fn ($q, $area) => $q->where('area', $area))
            ->when($data['q'] ?? null, fn ($q, $text) => $q->where('title', 'like', '%'.addcslashes($text, '%_\\').'%'))
            ->orderByDesc('first_seen_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (WatchdogProblem $p) => $this->problem($p))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function fix(Request $request, WatchdogProblem $problem): JsonResponse
    {
        abort_if($problem->fixed_at !== null, 422, 'This problem is already closed.');
        $this->watchdog->markFixed($problem, $this->staff($request));

        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, WatchdogProblem $problem): JsonResponse
    {
        $hours = (int) $request->validate(['hours' => ['required', 'integer', Rule::in([1, 4, 12, 24, 72, 168])]])['hours'];
        abort_if($problem->fixed_at !== null, 422, 'This problem is already closed.');
        $this->watchdog->mute($problem, $hours, $this->staff($request));

        return response()->json(['ok' => true, 'muted_until' => $problem->fresh()?->muted_until?->toIso8601String()]);
    }

    public function settings(): JsonResponse
    {
        $options = $this->settings->all();
        $checks = [];
        foreach (config('watchdog.checks') as $key => $check) {
            $checks[] = [
                'key' => $key,
                'area' => $check['area'],
                'label' => $check['label'],
                'help' => $check['help'],
                'every' => $check['every'],
                'level' => $check['level'],
                'unit' => $check['unit'] ?? null,
                'on' => $options['checks'][$key]['on'],
                'limit' => $options['checks'][$key]['limit'],
            ];
        }
        unset($options['checks']);

        return response()->json([
            'options' => $options,
            'telegram_token_set' => $this->settings->telegramToken() !== '', // the token itself never leaves the server
            'checks' => $checks,
            'areas' => self::AREAS,
            'backup_path_set' => (string) config('watchdog.backup_path') !== '',
            'mail_set' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $keys = array_keys(config('watchdog.checks'));
        $data = $request->validate([
            'email_on' => ['required', 'boolean'],
            'email_to' => ['nullable', 'email', 'max:190'],
            'telegram_on' => ['required', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:40', 'regex:/^-?\d+$|^@[A-Za-z0-9_]{5,32}$/'],
            'telegram_token' => ['nullable', 'string', 'max:100', 'regex:/^\d{5,15}:[A-Za-z0-9_-]{30,60}$/'],
            'remove_telegram_token' => ['sometimes', 'boolean'],
            'quiet_on' => ['required', 'boolean'],
            'quiet_from' => ['required', 'date_format:H:i'],
            'quiet_to' => ['required', 'date_format:H:i', 'different:quiet_from'],
            'digest_time' => ['required', 'date_format:H:i'],
            'reminder_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'heartbeat_url' => ['nullable', 'url:https', 'max:300'],
            'checks' => ['required', 'array'],
            'checks.*' => ['array'],
            'checks.*.on' => ['required', 'boolean'],
            'checks.*.limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'telegram_token.regex' => 'This does not look like a bot token from @BotFather (numbers, a colon, then letters).',
            'telegram_chat_id.regex' => 'The chat ID is a number (groups start with -), or a channel name like @mychannel.',
        ]);

        $checks = [];
        foreach ((array) $data['checks'] as $key => $check) {
            if (! in_array($key, $keys, true)) {
                continue; // unknown check names are ignored
            }
            $own = ['on' => (bool) $check['on']];
            $definition = WatchdogSettings::definition($key);
            if (array_key_exists('limit', $definition)) {
                $own['limit'] = self::sensibleLimit($key, (int) ($check['limit'] ?? $definition['limit']));
            }
            $checks[$key] = $own;
        }

        $user = $this->staff($request);
        $this->settings->save([
            'email_on' => (bool) $data['email_on'],
            'email_to' => (string) ($data['email_to'] ?? ''),
            'telegram_on' => (bool) $data['telegram_on'],
            'telegram_chat_id' => (string) ($data['telegram_chat_id'] ?? ''),
            'quiet_on' => (bool) $data['quiet_on'],
            'quiet_from' => $data['quiet_from'],
            'quiet_to' => $data['quiet_to'],
            'digest_time' => $data['digest_time'],
            'reminder_hours' => (int) $data['reminder_hours'],
            'heartbeat_url' => (string) ($data['heartbeat_url'] ?? ''),
            'checks' => $checks,
        ], $user->id);
        if (! empty($data['telegram_token'])) {
            $this->settings->saveToken((string) $data['telegram_token'], $user->id);
        } elseif ($request->boolean('remove_telegram_token')) {
            $this->settings->saveToken('', $user->id);
        }
        Activity::log('watchdog.settings', 'Changed the Watchdog settings');

        return $this->settings();
    }

    /** "Send test alert": to one channel or both, with the settings as they are saved now. */
    public function test(Request $request): JsonResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(['email', 'telegram'])]])['channel'];
        $channels = $this->settings->channels();
        $only = array_merge($channels, $channel === 'email' ? ['telegram_on' => false, 'email_on' => true] : ['email_on' => false, 'telegram_on' => true]);
        $ready = $channel === 'email' ? Notifier::emailReady($only) : Notifier::telegramReady($only);
        abort_unless($ready, 422, $channel === 'email' ? 'Add an email address first and save.' : 'Add the bot token and the chat ID first and save.');

        $result = app(Notifier::class)->send(Messages::test(), $only)[$channel];
        Activity::log('watchdog.test', "Sent a Watchdog test alert by {$channel}");

        return response()->json(['sent' => (bool) $result, 'message' => $result
            ? ($channel === 'email' ? 'Test email sent. Check your inbox (and spam folder).' : 'Test message sent. Check Telegram.')
            : ($channel === 'email' ? 'The email could not be sent. Check the mail settings in .env.' : 'Telegram refused it. Check the token and the chat ID, and that you pressed Start in the bot.')]);
    }

    /** Limits that would alert all the time are raised to the smallest sensible value. */
    private static function sensibleLimit(string $key, int $limit): int
    {
        return match ($key) {
            'health.uploads' => max(0, $limit),          // 0 = no limit
            'health.disk' => min(89, max(50, $limit)),   // 90% is always urgent
            'health.speed' => max(500, $limit),
            default => max(1, $limit),
        };
    }

    /** @return array<string, mixed> */
    private function problem(WatchdogProblem $p): array
    {
        return [
            'id' => $p->id,
            'key' => $p->key,
            'area' => $p->area,
            'level' => $p->level,
            'title' => $p->title,
            'message' => $p->message,
            'steps' => $p->steps ?? [],
            'count' => $p->count,
            'first_seen_at' => $p->first_seen_at->toIso8601String(),
            'last_seen_at' => $p->last_seen_at->toIso8601String(),
            'fixed_at' => $p->fixed_at?->toIso8601String(),
            'fixed_how' => $p->fixed_how,
            'fixed_by' => $p->relationLoaded('fixer') ? $p->fixer?->name : null,
            'muted_until' => $p->isMuted() ? $p->muted_until?->toIso8601String() : null,
            'muted_by' => $p->relationLoaded('muter') ? $p->muter?->name : null,
            'subject_type' => $p->subject_type,
            'subject_id' => $p->subject_id,
        ];
    }

    /** @param  Collection<int, WatchdogProblem>  $problems */
    private static function worst(Collection $problems): string
    {
        return $problems->contains('level', 'urgent') ? 'urgent' : ($problems->contains('level', 'warning') ? 'warning' : 'ok');
    }

    /**
     * @param  array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string}  $channels
     * @return array{email: bool, telegram: bool}
     */
    private static function channelSummary(array $channels): array
    {
        return ['email' => Notifier::emailReady($channels), 'telegram' => Notifier::telegramReady($channels)];
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
