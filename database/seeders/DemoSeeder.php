<?php

namespace Database\Seeders;

use App\Enums\AdStatus;
use App\Jobs\ProcessAdPhoto;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\AdPhoto;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\Report;
use App\Models\User;
use App\Support\Ads\AdCounters;
use App\Support\Features\FeatureManager;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

/**
 * Demo data for local testing and demos. NEVER runs in production.
 *
 *   php artisan iib:demo-reset            (recommended: removes old demo data first)
 *   php artisan db:seed --class=DemoSeeder
 *
 * Everything gets is_demo = true, so "Delete all demo data" removes exactly this.
 * Same result every time (fixed random seed).
 */
class DemoSeeder extends Seeder
{
    public const ADS = 300;

    public const PENDING = 24;

    public const USERS = 64;

    public const REPORTS = 18;

    public const HISTORY_DAYS = 110;

    /** Turn off for fast tests: ads are made without photos. */
    public bool $withPhotos = true;

    /** @var array<string, array{email:string, password:string, secret:string}> shown once at the end */
    public array $staffLogins = [];

    private Generator $faker;

    private CarbonImmutable $now;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data never runs in production (APP_ENV=production).');
        }

        if (! Category::exists()) {
            $this->call(DatabaseSeeder::class);
        }

        mt_srand(2026);
        $this->faker = Factory::create();
        $this->faker->seed(2026);
        $this->now = CarbonImmutable::now();

        $mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
        $areas = Area::where('city_id', $mogadishu->id)->pluck('id')->all();

        [$moderator, $support] = $this->staff();
        $users = $this->customers($mogadishu->id);
        $content = require database_path('demo/content.php');
        $categories = Category::with('fields')->whereIn('slug', array_keys($content))->get()->keyBy('slug');
        $photoPool = $this->photoPool();

        $made = [];
        Ad::withoutEvents(function () use (&$made, $content, $categories, $users, $mogadishu, $areas, $moderator, $photoPool) {
            for ($i = 0; $i < self::ADS + self::PENDING; $i++) {
                $slug = $this->pickCategory(array_keys($content));
                $category = $categories->get($slug);
                if (! $category) {
                    continue;
                }
                [$title, $min, $max] = $content[$slug][array_rand($content[$slug])];
                $ad = $this->ad($category, $title, $min, $max, $users[array_rand($users)], $mogadishu->id, $areas, $moderator, $i >= self::ADS);
                $this->photos($ad, $photoPool[$slug] ?? []);
                $made[] = $ad;
            }
        });

        $this->reports($made, $users, $moderator);
        $this->history($made, $moderator, $support, $users);

        app(FeatureManager::class)->set('site.demo_data', true);
        AdCounters::refreshAll();

        $this->command?->info(count($made).' demo ads, '.count($users).' demo users, '.self::REPORTS.' reports. "Show demo data" is ON.');
        foreach ($this->staffLogins as $role => $login) {
            $this->command?->line("  {$role}: {$login['email']}  password: {$login['password']}  2FA key: {$login['secret']}");
        }
    }

    // -----------------------------------------------------------------------

    /** Demo staff, with 2FA already set up (add the key to an authenticator app). */
    private function staff(): array
    {
        $out = [];
        foreach (['moderator' => 'Hodan Ali (demo)', 'support' => 'Abdi Warsame (demo)'] as $role => $name) {
            $password = Str::password(16, symbols: false);
            $secret = (new Google2FA)->generateSecretKey(32);
            $user = $this->userByEmail("demo-{$role}@demo.iibsom.test");
            $user->forceFill([
                'name' => $name, 'password' => $password, 'role' => $role, 'is_demo' => true, 'email_verified_at' => $this->now,
                'two_factor_secret' => $secret, 'two_factor_confirmed_at' => $this->now, 'two_factor_recovery_codes' => [],
                'created_at' => $this->now->subDays(self::HISTORY_DAYS + 5),
            ])->save();
            $this->staffLogins[$role] = ['email' => $user->email, 'password' => $password, 'secret' => $secret];
            $out[] = $user;
        }

        return $out;
    }

    private function userByEmail(string $email): User
    {
        return User::where('email', $email)->first() ?? (new User)->forceFill(['email' => $email]);
    }

    /** @return list<User> */
    private function customers(int $cityId): array
    {
        $first = ['Abdi', 'Ahmed', 'Mohamed', 'Hassan', 'Hussein', 'Ali', 'Omar', 'Yusuf', 'Ibrahim', 'Farah', 'Abdirahman', 'Abdullahi', 'Mustafa', 'Said', 'Liban',
            'Hodan', 'Amina', 'Fadumo', 'Hawa', 'Ifrah', 'Sahra', 'Khadija', 'Asha', 'Nasra', 'Ubah', 'Deeqa', 'Hibo', 'Muna', 'Ayaan', 'Ruqiya', 'Sagal', 'Halima'];
        $family = ['Warsame', 'Jama', 'Abdi', 'Nur', 'Osman', 'Aden', 'Guled', 'Elmi', 'Hirsi', 'Mohamud', 'Shire', 'Dahir', 'Ismail', 'Barre', 'Ahmed', 'Farah', 'Hassan'];
        $users = [];

        for ($i = 1; $i <= self::USERS; $i++) {
            $joined = $this->now->subDays(mt_rand(1, self::HISTORY_DAYS))->subMinutes(mt_rand(0, 1440));
            $user = $this->userByEmail("demo{$i}@demo.iibsom.test");
            $user->forceFill([
                'name' => $first[array_rand($first)].' '.$family[array_rand($family)],
                'phone' => '+25261'.str_pad((string) (1000000 + $i * 7919 % 8999999), 7, '0', STR_PAD_LEFT),
                'phone_has_whatsapp' => mt_rand(1, 10) > 2,
                'city_id' => $cityId,
                'role' => User::CUSTOMER,
                'is_demo' => true,
                'email_verified_at' => $joined,
                'terms_accepted_at' => $joined,
                'profile_completed_at' => $joined,
                'last_login_at' => $joined->addDays(mt_rand(0, 20))->min($this->now),
                'banned_at' => $i % 31 === 0 ? $this->now->subDays(mt_rand(1, 20)) : null,
                'ban_reason' => $i % 31 === 0 ? 'Scam ads (demo)' : null,
                'created_at' => $joined,
            ])->save();
            $users[] = $user;
        }

        return $users;
    }

    /** Popular categories get more ads, like on a real site. */
    private function pickCategory(array $slugs): string
    {
        $busy = ['cars' => 6, 'mobile-phones' => 6, 'houses-for-rent' => 5, 'land' => 4, 'houses-for-sale' => 3, 'goats-sheep' => 3, 'camels' => 3,
            'job-vacancies' => 3, 'laptops-computers' => 3, 'furniture' => 2, 'solar-generators' => 2, 'motorcycles' => 2];
        $weights = array_map(fn ($s) => $busy[$s] ?? 1, $slugs);
        $roll = mt_rand(1, array_sum($weights));
        foreach ($slugs as $k => $slug) {
            if (($roll -= $weights[$k]) <= 0) {
                return $slug;
            }
        }

        return $slugs[0];
    }

    private function ad(Category $category, string $title, int $min, int $max, User $user, int $cityId, array $areas, User $moderator, bool $pending): Ad
    {
        $age = $pending ? mt_rand(5, 60 * 30) : mt_rand(60 * 3, self::HISTORY_DAYS * 1440); // minutes
        $created = $this->now->subMinutes($age);
        $reviewed = $created->addMinutes(mt_rand(20, 300))->min($this->now);
        $days = $age / 1440;

        $status = match (true) {
            $pending => AdStatus::Pending,
            mt_rand(1, 100) <= 5 => AdStatus::Rejected,
            $days > 60 => [AdStatus::Expired, AdStatus::Expired, AdStatus::Sold, AdStatus::Live][mt_rand(0, 3)],
            mt_rand(1, 100) <= 8 => AdStatus::Sold,
            default => AdStatus::Live,
        };

        $price = $max > 0 ? $this->nicePrice(mt_rand($min, $max)) : null;

        $ad = new Ad([
            'category_id' => $category->id,
            'city_id' => $cityId,
            'area_id' => $areas ? $areas[array_rand($areas)] : null,
            'title' => $title,
            'description' => $this->description($category, $title),
            'price' => $price,
            'price_negotiable' => $price === null || mt_rand(1, 3) === 1,
            'condition' => $category->has_condition ? (mt_rand(1, 3) === 1 ? 'new' : 'used') : null,
            'fields' => $this->fields($category, $title),
        ]);
        $ad->forceFill([
            'user_id' => $user->id,
            'slug' => Str::limit(Str::slug($title), 100, ''),
            'status' => $status,
            'is_demo' => true,
            'content_hash' => hash('sha256', 'demo|'.Str::uuid()),
            'created_at' => $created,
            'updated_at' => $reviewed,
        ]);

        if ($status !== AdStatus::Pending) {
            $ad->reviewed_by = $moderator->id;
            $ad->reviewed_at = $reviewed;
        }
        if ($status === AdStatus::Rejected) {
            $ad->rejection_reason = ['Photos are not clear or not real', 'Wrong category', 'Price is missing or not real', 'Duplicate ad'][mt_rand(0, 3)];
        }
        if (in_array($status, [AdStatus::Live, AdStatus::Sold, AdStatus::Expired], true)) {
            $ad->published_at = $reviewed;
            $ad->expires_at = $status === AdStatus::Live && $days > 55
                ? $this->now->addDays(mt_rand(3, 50))        // renewed by the seller
                : $reviewed->addDays(60);
            if ($status === AdStatus::Expired) {
                $ad->expires_at = $this->now->subDays(mt_rand(1, max(1, (int) $days - 60)));
            }
        }
        if ($status === AdStatus::Sold) {
            $ad->sold_at = $reviewed->addDays(mt_rand(1, 20))->min($this->now);
        }

        $ad->save();

        return $ad;
    }

    private function nicePrice(int $price): int
    {
        $step = match (true) {
            $price >= 10000 => 500, $price >= 1000 => 50, $price >= 100 => 10, default => 1,
        };

        return max($step, (int) (round($price / $step) * $step));
    }

    private function description(Category $category, string $title): string
    {
        $intro = [
            "{$title}. Very good condition.",
            "Selling {$title}. Everything works well.",
            "{$title}, clean and ready.",
            "I am selling {$title}.",
        ];
        $middle = [
            'You can come and see it before you pay.', 'No problems, well looked after.', 'Price is a little negotiable for serious buyers.',
            'Delivery possible inside Mogadishu.', 'Original, not a copy.', 'Bought last year, used with care.', 'Many more in stock.',
            'Good for family use.', 'We are open every day from 8am to 9pm.', 'Serious buyers only, please.',
        ];
        $end = ['Call or WhatsApp.', 'Call me for more details.', 'WhatsApp me for more photos.', 'Thank you.'];
        $parent = $category->parent?->slug;
        if (in_array($parent, ['jobs', 'services'], true)) {
            $middle = ['Many years of experience.', 'Fast, honest and good work.', 'Available every day, also weekends.', 'Good references.', 'Salary depends on experience.', 'Send your CV by WhatsApp.'];
            $intro = ["{$title}.", "{$title}, in Mogadishu.", "{$title}. Please read below."];
        }
        shuffle($middle);

        return $intro[array_rand($intro)]."\n\n".implode(' ', array_slice($middle, 0, mt_rand(2, 4)))."\n\n".$end[array_rand($end)];
    }

    /** Custom field values that make sense (brand from the title when it is there). */
    private function fields(Category $category, string $title): array
    {
        $values = [];
        foreach ($category->allFields() as $field) {
            $values[$field->key] = match ($field->type) {
                'select' => $this->pickOption($field->optionList(), $title),
                'boolean' => (bool) mt_rand(0, 1),
                'number' => match ($field->key) {
                    'year' => preg_match('/\b(19|20)\d{2}\b/', $title, $m) ? (int) $m[0] : mt_rand(2006, 2022),
                    'mileage' => mt_rand(30, 260) * 1000,
                    'bedrooms' => preg_match('/(\d) bedroom/', $title, $m) ? (int) $m[1] : mt_rand(1, 5),
                    'bathrooms' => mt_rand(1, 3),
                    'size' => preg_match('/(\d+)x(\d+)/', $title, $m) ? (int) $m[1] * (int) $m[2] : mt_rand(8, 60) * 10,
                    'quantity' => preg_match('/(\d+) (heads|birds|pieces)/', $title, $m) ? (int) $m[1] : mt_rand(1, 30),
                    default => mt_rand(1, 100),
                },
                default => $field->key === 'model' ? trim(preg_replace('/^\S+\s+(\S+(\s\S+)?).*/u', '$1', $title), ' ,') : null,
            };
        }

        return array_filter($values, fn ($v) => $v !== null && $v !== '');
    }

    private function pickOption(array $options, string $title): ?string
    {
        foreach ($options as $value => $label) {
            if ($label !== 'Other' && stripos($title, (string) $label) !== false) {
                return (string) $value;
            }
        }
        $keys = array_keys($options);

        return $keys ? (string) $keys[array_rand($keys)] : null;
    }

    // ---- photos -----------------------------------------------------------

    /** @return array<string, list<string>> category slug => photo files (from tools/download-demo-photos) */
    private function photoPool(): array
    {
        if (! $this->withPhotos || ! is_file($json = database_path('demo/photos.json'))) {
            return [];
        }
        $pool = [];
        foreach (json_decode(file_get_contents($json), true) ?: [] as $p) {
            if (is_file(database_path('demo/photos/'.$p['file']))) {
                $pool[$p['category']][] = $p['file'];
            }
        }

        return $pool;
    }

    /** @var array<string, array{disk:string, variants:array, width:?int, height:?int}> each photo is made into sizes only once */
    private array $processed = [];

    private function photos(Ad $ad, array $files): void
    {
        if (! $files || mt_rand(1, 12) === 1) {
            return; // some sellers post without photos
        }
        shuffle($files);
        foreach (array_slice($files, 0, mt_rand(1, min(3, count($files)))) as $sort => $file) {
            $made = $this->processed[$file] ??= $this->process($ad, $file);
            if (! $made) {
                continue;
            }
            AdPhoto::create(['ad_id' => $ad->id, 'user_id' => $ad->user_id, 'sort' => $sort, 'status' => 'ready'] + $made);
        }
    }

    private function process(Ad $ad, string $file): ?array
    {
        $upload = 'uploads/demo/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($upload, file_get_contents(database_path('demo/photos/'.$file)));
        $photo = AdPhoto::create(['user_id' => $ad->user_id, 'upload_path' => $upload, 'status' => 'processing']);

        rescue(fn () => ProcessAdPhoto::dispatchSync($photo->id), report: false);
        $photo->refresh();
        $photo->delete(); // only used to make the sizes; the ad gets its own rows

        return $photo->status === 'ready' ? ['disk' => $photo->disk, 'variants' => $photo->variants, 'width' => $photo->width, 'height' => $photo->height] : null;
    }

    // ---- reports + history -----------------------------------------------

    private function reports(array $ads, array $users, User $moderator): void
    {
        $live = array_values(array_filter($ads, fn (Ad $a) => $a->status === AdStatus::Live));
        $texts = ['scam' => 'He asked me to send money first.', 'wrong_category' => 'This is not in the right category.', 'sold' => 'Seller told me it is already sold.',
            'offensive' => 'Bad words in the description.', 'other' => 'Photos look copied from the internet.'];
        $made = 0;
        for ($try = 0; $made < self::REPORTS && $try < 200; $try++) {
            $ad = $live[array_rand($live)];
            $by = $users[array_rand($users)];
            if ($by->id === $ad->user_id || Report::where('ad_id', $ad->id)->where('user_id', $by->id)->exists()) {
                continue;
            }
            $reason = array_rand($texts);
            $at = CarbonImmutable::parse($ad->published_at)->addHours(mt_rand(2, 200))->min($this->now);
            $handled = $made % 3 === 2;
            $report = new Report(['reason' => $reason, 'details' => $texts[$reason]]);
            $report->forceFill([
                'ad_id' => $ad->id, 'user_id' => $by->id, 'is_demo' => true, 'created_at' => $at, 'updated_at' => $at,
                'status' => $handled ? 'dismissed' : 'waiting',
                'handled_by' => $handled ? $moderator->id : null, 'handled_at' => $handled ? $at->addHours(3)->min($this->now) : null,
                'staff_note' => $handled ? 'Checked with the seller, the ad is fine.' : null,
            ])->save();
            $made++;
        }
    }

    /** Admin activity log for the last months (approvals, rejections, bans). */
    private function history(array $ads, User $moderator, User $support, array $users): void
    {
        $rows = [];
        foreach ($ads as $ad) {
            if (! $ad->reviewed_at) {
                continue;
            }
            $rejected = $ad->status === AdStatus::Rejected;
            $rows[] = [
                'user_id' => $moderator->id, 'action' => $rejected ? 'ad.rejected' : 'ad.approved', 'subject_type' => 'ad', 'subject_id' => $ad->id,
                'summary' => ($rejected ? 'Rejected' : 'Approved').' ad "'.Str::limit($ad->title, 60).'"'.($rejected ? ': '.$ad->rejection_reason : ''),
                'details' => null, 'ip' => null, 'is_demo' => true, 'created_at' => $ad->reviewed_at,
            ];
        }
        foreach ($users as $user) {
            if ($user->banned_at) {
                $rows[] = ['user_id' => $support->id, 'action' => 'user.banned', 'subject_type' => 'user', 'subject_id' => $user->id,
                    'summary' => "Banned {$user->name}: {$user->ban_reason}", 'details' => null, 'ip' => null, 'is_demo' => true, 'created_at' => $user->banned_at];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            AdminActivity::insert($chunk);
        }
    }
}
