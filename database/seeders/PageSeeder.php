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

        $somali = [
            'about' => ['Nagu saabsan iibsom', "iibsom waa meel bilaash ah oo lagu iibsado laguna iibiyo Soomaaliya: baabuur, guryo, dhul, taleefanno, xoolo, adeegyo iyo shaqooyin.\n\nXayeysiis kasta kooxdayada ayaa hubisa ka hor inta uusan muuqan, si aad u aragto xayeysiisyo dhab ah oo ka yimid dad dhab ah.\n\nSu'aalo ma qabtaa? Isticmaal macluumaadka xiriirka ee hoose."],
            'safety' => ['Ammaan ahaw', "## Ka hor intaadan lacag bixin\n- **Ku kulma meel dadweyne oo mashquul ah** maalinnimo.\n- **Marka hore arag alaabta.** Hubi inay shaqeyso ka hor intaadan bixin.\n- **Weligaa lacag hore ha dirin** (EVC, Zaad, Sahal, eDahab) wax aadan arag.\n- Baabuurta, guryaha iyo dhulka, **hubi waraaqaha** iyo cidda dhab ahaan leh.\n\n## Calaamadaha digniinta\n- Qiimuhu aad buu uga hooseeyaa sida caadiga ah.\n- Iibiyuhu wuu degdegayaa ama ma rabo inuu kula kulmo.\n- Waxay ku weydiinayaan lacag hore, khidmad ama PIN-kaaga.\n\n## Wax khaldan ma jiraa?\nRiix **Ka warbixi xayeysiiskan** bogga xayeysiiska. Kooxdayadu way hubisaa warbixin kasta."],
            'terms' => ['Shuruudaha isticmaalka', "**Qabyo: mulkiilaha ayaa hubin doona ka hor furitaanka.**\n\n1. Waa inaad xayeysiisyadaada ku qortaa macluumaad run ah.\n2. Dhig oo keliya waxyaabaha laguu oggol yahay inaad iibiso. Hub, maandooriye, alaab la xaday ama waxyaabaha dadka waaweyn looma oggola.\n3. iibsom kama qayb qaato iibka. Iibsadaha iyo iibiyuhu si toos ah ayay u wada macaamilaan.\n4. Waxaan beddeli karnaa ama saari karnaa xayeysiisyada, waxaana xiri karnaa akoonnada jebiya xeerarkan.\n5. Xayeysiisyadu waxay dhacaan maalmo go'an kadib, waana la cusbooneysiin karaa."],
            'privacy' => ['Qarsoodiga', "**Qabyo: mulkiilaha ayaa hubin doona ka hor furitaanka.**\n\n- **Waxa aan keydinno:** magacaaga, iimaylkaaga (Google), magaaladaada iyo lambarkaaga taleefanka, iyo xayeysiisyada aad dhigto.\n- **Cidda arkaysa:** iibsadayaashu waxay arkaan magacaaga, magaaladaada iyo lambarkaaga (marka ay riixaan \"Muuji lambarka\").\n- **Booqdayaasha aan galin:** ma keydinno cinwaankooda IP.\n- **Sawirrada:** sawir kasta waxaan ka saarnaa xogta qarsoon sida goobta GPS.\n- **Tirtir xogtaada:** nala soo xiriir, akoonkaaga ayaan tirtiri doonnaa."],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            $page = Page::firstOrNew(['slug' => $slug]);
            if (! $page->exists) {
                $page->enabled = true;
            }
            // Only add what is missing: text edited in the admin stays as it is.
            $page->title = ($page->title ?? []) + ['en' => $title, 'so' => $somali[$slug][0]];
            $page->body = ($page->body ?? []) + ['en' => $body, 'so' => $somali[$slug][1]];
            $page->save();
        }
    }
}
