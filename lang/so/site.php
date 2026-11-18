<?php

// Somali texts. Same keys as lang/en/site.php.
// Missing keys show in English automatically.
// Every text here "needs native review" until its key is listed in
// lang/so/_reviewed.php. Export a review sheet: php artisan iibsom:translations:export so

return [
    'meta_description' => 'Ka iibso oo iibi baabuur, guryo, dhul, taleefanno, xoolo, adeegyo iyo shaqooyin Soomaaliya. Bilaash oo fudud.',

    'nav' => [
        'home' => 'Bogga hore',
        'post_ad' => 'Dhig xayeysiis',
        'sell' => 'iibi', // lowercase: a capital I looks like l
        'saved' => 'Xayeysiisyada la keydiyey',
        'messages' => 'Fariimaha',
        'alerts' => 'Ogeysiisyo',
        'my_account' => 'Akoonkayga',
        'profile' => 'Akoon',
        'sign_in' => 'Gal',
        'language' => 'Luqadda',
        'skip_to_content' => 'U gudub nuxurka',
    ],

    // Auto-post text (Facebook / Telegram).
    'social' => [
        'line' => ':price · :city · :category',
    ],

    'home' => [
        'title' => 'Ka iibso oo iibi Soomaaliya',
        'heading' => 'Maxaad raadinaysaa?',
        'search_placeholder' => 'Waxaan raadinayaa...',
        'search_button' => 'Raadi',
        'location_label' => 'Goobta',
        'all_somalia' => 'Soomaaliya oo dhan',
        'categories' => 'Qaybaha',
        'popular_categories' => 'Qaybaha ugu badan',
        'trending_ads' => 'Xayeysiisyadii ugu dambeeyey',
        'popular_now' => 'Hadda caan ah',
        'popular_now_note' => 'Kuwa ugu badan ee la daawaday 30kii maalmood ee u dambeeyay',
        'for_you' => 'Adiga laguu soo jeediyey',
        'recently_viewed' => 'Wixii aad dhowaan eegtay',
        'clear_history' => 'Tirtir taariikhdayda',
        'history_note' => 'Waxay ku salaysan tahay wixii aad eegtay. Liiskan waxa uu ku jiraa qalabkan oo keliya.',
        'how_to_sell' => 'Sida loo iibiyo',
        'post_ad_tile' => 'Dhig xayeysiis',
        'trending' => 'Kuwa caanka ah',
        'safety_tips' => 'Ammaan ahaw',
        'no_ads_title' => 'Weli xayeysiis ma jiro',
        'no_ads_text' => 'Noqo qofka ugu horreeya ee xayeysiis dhiga. Waa bilaash, wax ka yar hal daqiiqo ayuu qaataa.',
    ],

    'page_updated' => 'Markii ugu dambeysay ee la cusbooneysiiyey: :date',

    'ads_count' => '{0} Weli xayeysiis ma jiro|{1} :count xayeysiis|[2,*] :count xayeysiis',

    'contact' => [
        'title' => 'Nala soo xiriir',
        'intro' => 'Su\'aal, dhibaato ama fikrad ma qabtaa? Noo qor. Iimayl ayaan ku jawaabnaa, inta badan 2 maalmood oo shaqo gudahood.',
        'help_first' => 'Jawaabo badan ayaa horey ugu jira',
        'help_link' => 'Xarunta caawimaadda',
        'name' => 'Magacaaga',
        'email' => 'Iimaylkaaga',
        'email_help' => 'Jawaabtayada halkan ayaan u diraynaa.',
        'topic' => 'Ku saabsan',
        'topics' => [
            'ad' => 'Xayeysiis (kayga ama mid kale)',
            'account' => 'Akoonkayga ama galitaanka',
            'problem' => 'Wax aan shaqeynayn',
            'business' => 'Ganacsi, dukaamo ama xayeysiin',
            'other' => 'Wax kale',
        ],
        'message' => 'Fariintaada',
        'send' => 'Dir',
        'thanks' => 'Mahadsanid. Fariintaada waan helnay, iimayl ayaan kugu soo jawaabi doonnaa.',
        'fix' => 'Fadlan hubi sanduuqyada casaanka lagu calaamadeeyey.',
        'sum_wrong' => 'Jawaabtaasi ma saxna. Fadlan isku day mar kale.',
        'privacy' => 'Fariintaada waxaan hayn doonnaa 180 maalmood si aan ugu jawaabno. Ciwaanka IP-gaaga ma hayno.',
    ],

    'search' => [
        'open' => 'Raadi',
        'in_category' => 'Ka raadi :category',
        'recent' => 'Raadintaadii ugu dambeysay',
        'clear_recent' => 'Tirtir',
        'in_scope' => ':text gudaha :category',
        'category' => 'Qayb',
        'count' => '{0} Talo ma jirto|{1} :count talo|[2,*] :count talo',
    ],

    'footer' => [
        'about' => 'Nagu saabsan',
        'terms' => 'Shuruudaha',
        'privacy' => 'Qarsoodiga',
        'help' => 'Caawimaad',
        'safety' => 'Talooyin ammaan',
        'rights' => 'Xuquuqda oo dhan way dhowran tahay.',
        'col_about' => 'Nagu saabsan',
        'col_support' => 'Caawimaad',
        'col_popular' => 'Kuwa la jecel yahay',
        'col_follow' => 'Na raac',
        'cookies' => 'Macluumaadka kukiyada',
        'copyright' => 'Xuquuqda & dhibaatooyinka',
        'contact' => 'Nala soo xiriir',
        'faq' => "Xarunta caawimaadda & su'aalaha",
        'language' => 'Luqadda',
        'tagline' => 'Ka iibso kana iibi Soomaaliya.',
    ],

    'errors' => [
        'not_found_title' => 'Boggan lama helin',
        'not_found_text' => 'Boggan ma jiro ama waa la saaray.',
        'back_home' => 'Ku noqo bogga hore',
        'bad_text' => 'Xarfaha qaarkood lama akhrin karo. Fadlan mar kale qor.',
        'forbidden_title' => 'Ma furi kartid boggan',
        'forbidden_text' => 'Boggan uma furna akoonkaaga.',
        'expired_title' => 'Boggan wuu furnaa muddo dheer',
        'expired_text' => 'Ammaankaaga darteed, foomamku way joogsadaan muddo kadib. Dib u noqo, bogga cusboonaysii oo mar kale isku day.',
        'too_many_title' => 'Isku day aad u badan',
        'too_many_text' => 'Fadlan sug dhowr daqiiqo, kadibna mar kale isku day.',
        'server_title' => 'Wax baa khaldamay',
        'server_text' => 'Ciladdu waa dhankayaga, ma aha dhankaaga. Fadlan mar kale isku day daqiiqad kadib.',
        'down_title' => 'iibsom waa la cusboonaysiinayaa',
        'down_text' => 'Waxaan soo laaban doonnaa dhowr daqiiqo kadib.',
    ],

    'banner_ad' => 'Xayeysiis',

    'help' => [
        'title' => 'Caawimaad',
        'heading' => 'Sideen kuu caawin karnaa?',
        'intro' => 'Jawaabo gaagaaban oo su\'aalaha caadiga ah.',
        'more' => 'Caawimaad kale',
        'all' => 'Dhammaan caawimaadda',
        'still_need' => 'Weli caawimaad ma u baahan tahay?',
        'write_to' => 'Noogu soo qor:',
    ],

    'tour' => [
        'label' => 'Socod gaaban',
        'step' => 'Tallaabada :n ee :total',
        'next' => 'Xiga',
        'back' => 'Dib',
        'done' => 'Bilow',
        'skip' => 'Ka gudub',
        'find_title' => 'Ka hel meel kuu dhow',
        'find_text' => 'Raadi baabuur, taleefanno, guryo iyo wax kale. Dooro magaaladaada si aad u aragto xayeysiisyada kuu dhow.',
        'sell_title' => 'Ku iibi bilaash',
        'sell_text' => 'Riix iibi, ku dar sawirro, qiime iyo magaaladaada. Kooxdayadu waxay hubisaa xayeysiis kasta, kadibna iibsadayaashu way ku soo wacaan ama kugula xiriiraan WhatsApp.',
        'safe_title' => 'Ammaan ahaw',
        'safe_text' => 'Ku kulma meel mashquul ah oo arag alaabta ka hor intaadan lacag bixin. Weligaa lacag hore ha dirin.',
    ],
];
