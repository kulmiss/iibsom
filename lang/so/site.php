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

    'footer' => [
        'about' => 'Nagu saabsan',
        'terms' => 'Shuruudaha',
        'privacy' => 'Qarsoodiga',
        'help' => 'Caawimaad',
        'safety' => 'Talooyin ammaan',
        'rights' => 'Xuquuqda oo dhan way dhowran tahay.',
    ],

    'errors' => [
        'not_found_title' => 'Boggan lama helin',
        'not_found_text' => 'Boggan ma jiro ama waa la saaray.',
        'back_home' => 'Ku noqo bogga hore',
        'bad_text' => 'Xarfaha qaarkood lama akhrin karo. Fadlan mar kale qor.',
    ],

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
