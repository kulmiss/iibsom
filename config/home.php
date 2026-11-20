<?php

/*
 * The parts of the home page, top to bottom, for Admin → Home page (PR 86).
 * Each part is shown or hidden by its own feature switch ('key'). 'needs' = other switches that must
 * also be ON before the part can show (it also hides itself when it has nothing to show).
 * 'fixed' parts are always shown: they are how people find ads.
 */
return [
    'sections' => [
        ['area' => 'top', 'fixed' => true, 'label' => 'Big title and search box', 'description' => 'What are you looking for? (with the city picker when 2+ cities are live)'],
        ['area' => 'top', 'key' => 'home.promise'],
        ['area' => 'top', 'key' => 'search.location_picker'],
        ['area' => 'top', 'key' => 'home.popular_searches'],
        ['area' => 'top', 'key' => 'home.post_button', 'needs' => ['ads.posting']],
        ['area' => 'main', 'fixed' => true, 'label' => 'Categories', 'description' => 'The list on computers and the picture tiles on phones.'],
        ['area' => 'main', 'key' => 'home.sticky_categories'],
        ['area' => 'main', 'key' => 'home.quick_links'],
        ['area' => 'main', 'key' => 'site.banners', 'note' => 'Add or change the pictures in Home banners.'],
        ['area' => 'main', 'key' => 'home.popular_categories'],
        ['area' => 'main', 'key' => 'home.recommended', 'needs' => ['recommendations']],
        ['area' => 'main', 'key' => 'home.featured', 'needs' => ['boosts']],
        ['area' => 'main', 'key' => 'home.popular_now', 'needs' => ['ad_stats']],
        ['area' => 'main', 'fixed' => true, 'label' => 'Latest ads', 'description' => 'The newest live ads, with "Show more ads".'],
        ['area' => 'lower', 'key' => 'home.places'],
        ['area' => 'lower', 'key' => 'home.trusted_sellers', 'needs_any' => ['shops', 'verified_badge']],
        ['area' => 'lower', 'key' => 'home.how_it_works'],
        ['area' => 'lower', 'key' => 'home.trust_strip'],
    ],
];
