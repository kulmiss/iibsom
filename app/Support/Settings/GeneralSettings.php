<?php

namespace App\Support\Settings;

use App\Console\Commands\SendWeeklySummary;
use App\Support\Cache\PageCache;
use App\Support\Site\Footer;
use Illuminate\Validation\Rule;

/**
 * Settings → General, shared by the settings page and the first-time setup
 * wizard, so both check and save the values the same way.
 */
class GeneralSettings
{
    public function __construct(private readonly SettingsManager $settings) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'ads_expiry_days' => ['required', 'integer', 'min:7', 'max:365'],
            'ads_max_per_day' => ['required', 'integer', 'min:1', 'max:100'],
            'ads_max_photos' => ['required', 'integer', 'min:1', 'max:20'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => [Rule::in(array_keys(config()->array('iibsom.locales')))],
            'bad_words' => ['nullable', 'string', 'max:20000'],
            'weekly_emails' => ['nullable', 'array', 'max:5'],
            'weekly_emails.*' => ['email:rfc', 'max:120'],
            'support_email' => ['nullable', 'email:rfc', 'max:120'],
            'payments_test_mode' => ['sometimes', 'boolean'],
            'payment_numbers' => ['sometimes', 'array'],
            'payment_numbers.*' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +\-]*$/'],
            'diaspora_rates' => ['sometimes', 'array'],
            'diaspora_rates.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'footer_social' => ['sometimes', 'array'],
            'footer_social.*' => ['nullable', 'string', 'max:200'],
            'footer_links' => ['sometimes', 'array'],
            'footer_links.*' => [Rule::in(array_keys(Footer::LINKS))],
        ];
    }

    /**
     * Real money needs somewhere to send it: test mode can only be turned off
     * when at least one merchant number is filled in.
     *
     * @param  array<string, mixed>  $data
     */
    public static function paymentProblem(array $data): ?string
    {
        $testMode = array_key_exists('payments_test_mode', $data) ? (bool) $data['payments_test_mode'] : (bool) setting('payments.test_mode');
        $numbers = array_key_exists('payment_numbers', $data) ? (array) $data['payment_numbers'] : (array) setting('payments.numbers');
        if (! $testMode && array_filter(array_map(fn ($n) => trim((string) $n), $numbers)) === []) {
            return 'Add at least one merchant number before turning test mode off.';
        }

        return null;
    }

    /**
     * A social link must be an https address on that network's own site (no look-alike or other pages).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string> field => message
     */
    public static function socialProblems(array $data): array
    {
        $problems = [];
        foreach ((array) ($data['footer_social'] ?? []) as $network => $url) {
            $url = trim((string) $url);
            if (! isset(Footer::NETWORKS[$network])) {
                $problems['footer_social'] = 'Unknown social network.';
            } elseif ($url !== '' && ! Footer::allowed((string) $network, $url)) {
                $problems['footer_social.'.$network] = 'The '.Footer::NETWORKS[$network][0].' link must start with https:// and be on '.implode(' or ', Footer::NETWORKS[$network][1]).'.';
            }
        }

        return $problems;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'weekly_emails.*.email' => 'Each weekly summary address must be a valid email.',
            'support_email.email' => 'The help email must be a valid email address.',
            'payment_numbers.*.regex' => 'A merchant number can only have numbers, spaces, + and -.',
            'diaspora_rates.*.numeric' => 'Each exchange rate must be a number, e.g. 0.92.',
            'diaspora_rates.*.min' => 'An exchange rate cannot be below 0.',
        ];
    }

    /**
     * Save validated values. Optional ones (bad words, emails) change only
     * when they were sent, so a form without them keeps them as they are.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, int $userId): void
    {
        $languages = array_values(array_unique([config('iibsom.default_locale'), ...(array) $data['languages']]));

        $this->settings->set('ads.expiry_days', (int) $data['ads_expiry_days'], $userId);
        $this->settings->set('ads.max_per_day', (int) $data['ads_max_per_day'], $userId);
        $this->settings->set('ads.max_photos', (int) $data['ads_max_photos'], $userId);
        $this->settings->set('languages.enabled', $languages, $userId);

        if (array_key_exists('bad_words', $data)) {
            $words = collect(preg_split('/\r?\n/', (string) ($data['bad_words'] ?? '')) ?: [])
                ->map(fn ($w) => mb_strtolower(trim($w)))->filter()->unique()->take(1000)->values()->all();
            $this->settings->set('safety.bad_words', $words, $userId);
        }

        if (array_key_exists('weekly_emails', $data)) {
            $emails = array_values(array_unique(array_map('mb_strtolower', array_filter((array) ($data['weekly_emails'] ?? []), 'is_string'))));
            $this->settings->set('reports.weekly_emails', $emails, $userId);
        }

        if (array_key_exists('payment_numbers', $data)) {
            $numbers = [];
            foreach (array_keys(config()->array('boosts.methods')) as $method) {
                $numbers[$method] = trim((string) ($data['payment_numbers'][$method] ?? ''));
            }
            $this->settings->set('payments.numbers', $numbers, $userId);
        }
        if (array_key_exists('payments_test_mode', $data)) {
            $this->settings->set('payments.test_mode', (bool) $data['payments_test_mode'], $userId);
        }

        if (array_key_exists('diaspora_rates', $data)) {
            $rates = [];
            foreach (array_keys(config()->array('iibsom.diaspora_currencies')) as $code) {
                $rates[$code] = round((float) ($data['diaspora_rates'][$code] ?? 0), 6);
            }
            if ($rates != setting('diaspora.rates')) {
                $this->settings->set('diaspora.rates', $rates, $userId);
                $this->settings->set('diaspora.rates_date', now('Africa/Mogadishu')->toDateString(), $userId);
                PageCache::bump(); // the rates are inside cached ad pages
            }
        }

        if (array_key_exists('support_email', $data)) {
            $this->settings->set('site.support_email', mb_strtolower(trim((string) ($data['support_email'] ?? ''))), $userId);
            PageCache::bump(); // "Contact us" is in the footer of every cached page
        }

        if (array_key_exists('footer_social', $data)) {
            $social = [];
            foreach (array_keys(Footer::NETWORKS) as $network) {
                $social[$network] = trim((string) ($data['footer_social'][$network] ?? ''));
            }
            $this->settings->set('footer.social', $social, $userId);
            PageCache::bump();
        }
        if (array_key_exists('footer_links', $data)) {
            // Kept in the admin's list order (the order of Footer::LINKS).
            $this->settings->set('footer.links', array_values(array_intersect(array_keys(Footer::LINKS), (array) ($data['footer_links'] ?? []))), $userId);
            PageCache::bump();
        }
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'ads_expiry_days' => setting('ads.expiry_days'),
            'ads_max_per_day' => setting('ads.max_per_day'),
            'ads_max_photos' => setting('ads.max_photos'),
            'languages' => setting('languages.enabled'),
            'available_languages' => collect(config()->array('iibsom.locales'))->map(fn ($l, $code) => ['code' => $code, 'name' => $l['name'], 'default' => $code === config('iibsom.default_locale')])->values(),
            'currency' => setting('currency'),
            'bad_words' => implode("\n", setting('safety.bad_words') ?? []),
            'weekly_emails' => SendWeeklySummary::recipients(),
            'weekly_last_sent' => setting('reports.weekly_last_sent') ?: null,
            'support_email' => setting('site.support_email'),
            'payments_test_mode' => (bool) setting('payments.test_mode'),
            'payment_numbers' => (object) setting('payments.numbers'),
            'payment_methods' => config()->array('boosts.methods'),
            'diaspora_rates' => (object) setting('diaspora.rates'),
            'diaspora_rates_date' => setting('diaspora.rates_date') ?: null,
            'diaspora_currencies' => config()->array('iibsom.diaspora_currencies'),
            'footer_social' => (object) setting('footer.social'),
            'footer_networks' => array_map(fn (array $n) => $n[0], Footer::NETWORKS),
            'footer_links' => setting('footer.links'),
            'footer_link_options' => Footer::LINKS,
        ];
    }
}
