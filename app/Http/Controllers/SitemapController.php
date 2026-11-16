<?php

namespace App\Http\Controllers;

use App\Search\DatabaseAdSearch;
use App\Support\Catalog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml (cached 1 hour) and robots.txt. Switched-off things are never listed. */
class SitemapController extends Controller
{
    public function sitemap(Catalog $catalog): Response
    {
        $xml = Cache::remember('iibsom.sitemap.'.(feature('site.demo_data') ? 'demo' : 'real'), 3600, function () use ($catalog) {
            $urls = [['loc' => url('/'), 'changefreq' => 'hourly']];
            foreach (['about', 'safety', 'terms', 'privacy'] as $page) {
                $urls[] = ['loc' => url('/'.$page), 'changefreq' => 'monthly'];
            }
            foreach ($catalog->categories() as $main) {
                $urls[] = ['loc' => url('/'.$main['slug']), 'changefreq' => 'hourly'];
                foreach ($main['children'] as $sub) {
                    $urls[] = ['loc' => url('/'.$sub['slug']), 'changefreq' => 'hourly'];
                }
            }
            DatabaseAdSearch::publicQuery()->with(['category:id,slug,parent_id,enabled', 'category.parent:id,enabled', 'city:id,slug'])
                ->latest('published_at')->limit(45000)->get(['id', 'slug', 'category_id', 'city_id', 'updated_at'])
                ->each(function ($ad) use (&$urls) {
                    if ($ad->category->isLive()) {
                        $urls[] = ['loc' => url("/{$ad->category->slug}/{$ad->city->slug}/{$ad->slug}-{$ad->id}"), 'lastmod' => $ad->updated_at?->toAtomString()];
                    }
                });

            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as $u) {
                $body .= '<url><loc>'.e($u['loc']).'</loc>'.(isset($u['lastmod']) ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '').(isset($u['changefreq']) ? '<changefreq>'.$u['changefreq'].'</changefreq>' : '').'</url>';
            }

            return $body.'</urlset>';
        });

        return response($xml)->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /post', 'Disallow: /account', 'Disallow: /welcome', 'Disallow: /search', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /']; // never index test sites

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
