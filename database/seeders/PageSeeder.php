<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter text for the 4 core pages. Only creates missing pages:
 * text edited in the admin is never overwritten.
 * The owner must check Terms and Privacy before launch (LATER.md).
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => ['About iibsom', "iibsom is a free place to buy and sell in Somalia: cars, houses, land, phones, livestock, services and jobs.\n\nEvery ad is checked by our team before it goes live, so you see real ads from real people.\n\nQuestions? Use the contact details below."],
            'safety' => ['Stay safe', "## Before you pay\n- **Meet in a busy, public place** in daylight.\n- **See the item first.** Check it works before you pay.\n- **Never send money in advance** (EVC, Zaad, Sahal, eDahab) for something you have not seen.\n- For cars, houses and land, **check the papers** and who really owns it.\n\n## Warning signs\n- The price is much lower than normal.\n- The seller is in a hurry or will not meet.\n- They ask for a deposit, a fee or your PIN.\n\n## Something wrong?\nPress **Report this ad** on the ad page. Our team checks every report."],
            'terms' => ['Terms of use', "**Draft: to be checked by the owner before launch.**\n\n1. You must give true information in your ads.\n2. Only post things you are allowed to sell. No weapons, drugs, stolen goods or adult content.\n3. iibsom does not take part in the sale. Buyers and sellers deal directly.\n4. We may change or remove ads and block accounts that break these rules.\n5. Ads end after a set number of days and can be renewed."],
            'privacy' => ['Privacy', "**Draft: to be checked by the owner before launch.**\n\n- **What we keep:** your name, email (from Google), city and phone number, and the ads you post.\n- **Who sees it:** buyers see your name, city and phone number (after they tap \"Show number\").\n- **Visitors who are not signed in:** we do not store their IP address.\n- **Photos:** we remove hidden data like GPS location from every photo.\n- **Delete your data:** contact us and we will remove your account."],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => ['en' => $title], 'body' => ['en' => $body], 'enabled' => true]);
        }
    }
}
