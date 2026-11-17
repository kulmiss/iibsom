<?php

namespace App\Support\Site;

use App\Support\Catalog;

/**
 * The site footer (switch "site.new_footer"): which links are ON, the social pages, popular links.
 * Everything is set in Admin → Settings → General → Footer.
 */
class Footer
{
    /** Social networks: key => [name, web addresses a link may point to]. Other addresses are refused. */
    public const NETWORKS = [
        'facebook' => ['Facebook', ['facebook.com', 'fb.com', 'fb.me']],
        'instagram' => ['Instagram', ['instagram.com']],
        'tiktok' => ['TikTok', ['tiktok.com']],
        'youtube' => ['YouTube', ['youtube.com', 'youtu.be']],
        'telegram' => ['Telegram', ['t.me', 'telegram.me']],
        'whatsapp' => ['WhatsApp', ['wa.me', 'whatsapp.com']],
    ];

    /** Footer links the Super Admin can turn on or off: key => name in the admin. */
    public const LINKS = [
        'about' => 'About us',
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'cookies' => 'Cookie info',
        'copyright' => 'Copyright / report a problem',
        'contact' => 'Contact us (the help email)',
        'safety' => 'Safety tips',
        'help' => 'Help centre / FAQ',
        'popular' => 'Popular categories and cities',
    ];

    public function __construct(private readonly Catalog $catalog) {}

    public function on(string $link): bool
    {
        return in_array($link, (array) setting('footer.links'), true);
    }

    /** Is this address on one of the network's own sites (https only)? */
    public static function allowed(string $network, string $url): bool
    {
        $hosts = self::NETWORKS[$network][1] ?? [];
        if (! str_starts_with(strtolower($url), 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{key: string, name: string, url: string, icon: string}> Only filled-in (and allowed) links. */
    public function socials(): array
    {
        $out = [];
        foreach (self::NETWORKS as $key => [$name]) {
            $url = trim((string) (((array) setting('footer.social'))[$key] ?? ''));
            if ($url !== '' && self::allowed($key, $url)) {
                $out[] = ['key' => $key, 'name' => $name, 'url' => $url, 'icon' => 'social-'.$key];
            }
        }

        return $out;
    }

    /**
     * The sub categories with the most live ads (popular ones first while the site is empty).
     *
     * @return list<array{slug: string, name: array<string, string>}>
     */
    public function popularCategories(int $limit = 6): array
    {
        $subs = [];
        foreach ($this->catalog->categories() as $main) {
            foreach ($main['children'] as $child) {
                $subs[] = $child;
            }
        }
        usort($subs, fn (array $a, array $b) => [$b['ads_count'], (int) $b['popular']] <=> [$a['ads_count'], (int) $a['popular']]);

        return array_map(fn (array $c) => ['slug' => $c['slug'], 'name' => $c['name']], array_slice($subs, 0, $limit));
    }

    /** @return list<array{slug: string, name: array<string, string>}> */
    public function popularCities(int $limit = 6): array
    {
        $cities = $this->catalog->cities();
        usort($cities, fn (array $a, array $b) => [(int) $b['popular'], $b['ads_count']] <=> [(int) $a['popular'], $a['ads_count']]);

        return array_map(fn (array $c) => ['slug' => $c['slug'], 'name' => $c['name']], array_slice($cities, 0, $limit));
    }
}
