<?php

namespace App\Console\Commands;

use App\Mail\WeeklySummaryMail;
use App\Support\Reports\WeeklySummary;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Weekly email to the owner (Monday morning, Mogadishu time; see routes/console.php).
 * Sent once per week: a second run for the same week does nothing (unless --force).
 */
class SendWeeklySummary extends Command
{
    protected $signature = 'iibsom:weekly-summary {--to= : Send only to this address (test)} {--force : Send again for a week already sent}';

    protected $description = 'Email last week\'s numbers to the addresses in Settings';

    public function handle(SettingsManager $settings): int
    {
        if (! feature('reports.weekly_email')) {
            $this->line('Weekly summary switch is OFF.');

            return self::SUCCESS;
        }

        $to = $this->option('to');
        $recipients = is_string($to) && $to !== '' ? [$to] : self::recipients();
        if ($recipients === []) {
            $this->line('No addresses in Settings → General → Weekly summary. Nothing sent.');

            return self::SUCCESS;
        }

        $from = WeeklySummary::lastWeek();
        $weekKey = $from->format('o-\WW');
        if (! $this->option('to') && ! $this->option('force') && $settings->get('reports.weekly_last_sent') === $weekKey) {
            $this->line("Already sent for {$weekKey}.");

            return self::SUCCESS;
        }

        $summary = WeeklySummary::build($from);
        $failed = 0;
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new WeeklySummaryMail($summary));
                $this->info("Sent to {$email}");
            } catch (Throwable $e) {
                $failed++;
                Log::error('weekly_summary.failed', ['to' => $email, 'error' => $e->getMessage()]);
                $this->error("Could not send to {$email}: {$e->getMessage()}");
            }
        }

        if (! $this->option('to') && $failed < count($recipients)) {
            $settings->set('reports.weekly_last_sent', $weekKey);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    public static function recipients(): array
    {
        $list = setting('reports.weekly_emails');

        return array_values(array_filter(is_array($list) ? $list : [], fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}
