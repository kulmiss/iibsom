<?php

namespace App\Console\Commands;

use App\Support\Ads\AdCounters;
use App\Support\Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fill a SEPARATE database with many users and ads to measure speed (load test, docs/load-test.md).
 * Refuses unless the database name ends in "_load", so it can never touch real or dev data.
 */
class MakeLoadData extends Command
{
    protected $signature = 'iibsom:load-data {--ads=100000} {--users=10000} {--force}';

    protected $description = 'Fill a *_load database with many users and ads for the load test (never anything else)';

    public function handle(): int
    {
        $database = (string) DB::connection()->getDatabaseName();
        if ($this->laravel->isProduction() || ! str_ends_with($database, '_load')) {
            $this->error("Refused: only for a database whose name ends in \"_load\" (this one is \"{$database}\").");

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Delete all ads and users in {$database} and make new ones?", true)) {
            return self::SUCCESS;
        }

        $adsWanted = max(1, (int) $this->option('ads'));
        $usersWanted = max(1, (int) $this->option('users'));
        mt_srand(2026); // the same data every time, so runs can be compared

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['ad_photos', 'ads', 'sessions'] as $table) {
            DB::table($table)->truncate();
        }
        DB::table('users')->where('role', 'customer')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $cities = array_column(app(Catalog::class)->cities(), 'id');
        $areas = DB::table('areas')->where('enabled', true)->get(['id', 'city_id'])->groupBy('city_id')->map(fn ($a) => $a->pluck('id')->all())->all();
        $categories = DB::table('categories')->whereNotNull('parent_id')->where('enabled', true)->get(['id', 'slug', 'has_condition'])->all();
        /** @var array<string, list<array{0: string, 1: int, 2: int}>> $texts */
        $texts = require database_path('demo/content.php');
        $password = Hash::make(Str::random(32)); // nobody signs in as these users

        $this->info("Making {$usersWanted} users…");
        $now = now();
        foreach (array_chunk(range(1, $usersWanted), 1000) as $chunk) {
            DB::table('users')->insert(array_map(fn (int $i) => [
                'name' => self::NAMES[$i % count(self::NAMES)].' '.self::NAMES[($i * 7) % count(self::NAMES)],
                'email' => "load{$i}@load.iibsom.test",
                'password' => $password,
                'role' => 'customer',
                'phone' => '+25261'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'phone_has_whatsapp' => true,
                'city_id' => $cities[$i % count($cities)],
                'terms_accepted_at' => $now,
                'profile_completed_at' => $now,
                'created_at' => $now->copy()->subDays($i % 400),
                'updated_at' => $now,
            ], $chunk));
        }
        $userIds = DB::table('users')->where('role', 'customer')->pluck('id')->all();

        $this->info("Making {$adsWanted} ads…");
        $bar = $this->output->createProgressBar($adsWanted);
        $statuses = array_merge(array_fill(0, 85, 'live'), array_fill(0, 5, 'pending'), array_fill(0, 5, 'expired'), array_fill(0, 5, 'sold'));
        for ($done = 0; $done < $adsWanted; $done += 1000) {
            $rows = [];
            for ($i = $done; $i < min($adsWanted, $done + 1000); $i++) {
                $category = $categories[mt_rand(0, count($categories) - 1)];
                $pool = $texts[$category->slug] ?? [['Good item for sale', 10, 500]];
                [$title, $low, $high] = $pool[mt_rand(0, count($pool) - 1)];
                $title = Str::limit($title.' #'.($i + 1), 80, '');
                $city = $cities[mt_rand(0, count($cities) - 1)];
                $status = $statuses[mt_rand(0, 99)];
                $published = $now->copy()->subMinutes(mt_rand(0, 180 * 24 * 60));
                $brand = Str::lower(Str::before($title, ' '));
                $rows[] = [
                    'user_id' => $userIds[mt_rand(0, count($userIds) - 1)],
                    'category_id' => $category->id,
                    'city_id' => $city,
                    'area_id' => isset($areas[$city]) ? $areas[$city][mt_rand(0, count($areas[$city]) - 1)] : null,
                    'title' => $title,
                    'slug' => Str::limit(Str::slug($title), 100, ''),
                    'description' => $title.'. '.self::LINES[$i % count(self::LINES)].' '.self::LINES[($i * 3) % count(self::LINES)],
                    'price' => mt_rand($low, $high),
                    'price_negotiable' => mt_rand(0, 4) === 0,
                    'condition' => $category->has_condition ? (mt_rand(0, 2) ? 'used' : 'new') : null,
                    'fields' => json_encode(['brand' => $brand]),
                    'status' => $status,
                    'content_hash' => hash('sha256', 'load'.$i),
                    'is_demo' => false,
                    'published_at' => $status === 'pending' ? null : $published,
                    'expires_at' => $status === 'pending' ? null : ($status === 'expired' ? $now->copy()->subDays(1) : $published->copy()->addDays(365)),
                    'sold_at' => $status === 'sold' ? $published->copy()->addDays(3) : null,
                    'created_at' => $published,
                    'updated_at' => $published,
                ];
            }
            DB::table('ads')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();

        AdCounters::refreshAll();
        app(Catalog::class)->flush();
        $this->info('Done: '.DB::table('users')->count().' users, '.DB::table('ads')->count().' ads.');

        return self::SUCCESS;
    }

    private const NAMES = ['Abdi', 'Ahmed', 'Amina', 'Asha', 'Bashir', 'Deeqa', 'Faisal', 'Farhan', 'Fartun', 'Hassan', 'Hodan', 'Ifrah', 'Ilhan', 'Khadija', 'Liban', 'Mohamed', 'Muna', 'Nasra', 'Omar', 'Sahra', 'Said', 'Yusuf', 'Zamzam', 'Warsame'];

    private const LINES = [
        'Very clean, used with care.', 'Price is fair, call me.', 'Can deliver in Mogadishu.', 'Come and see it first.',
        'Original, not a copy.', 'Good condition, no problems.', 'Selling because I am travelling.', 'Quick sale.',
    ];
}
