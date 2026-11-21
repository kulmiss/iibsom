<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Support\Localization\Locales;
use App\Support\Share\ShareImage;
use Symfony\Component\HttpFoundation\Response;

/**
 * /share/{id}-{version}-{lang}.jpg — the picture apps show when an ad is shared (PR 110, switch "share.image").
 * No session, no cookies. Only live ads. An old version (the ad changed) is sent to the new address.
 */
class ShareImageController extends Controller
{
    public function show(int $id, string $version, string $lang, Locales $locales): Response
    {
        abort_unless($locales->isEnabled($lang), 404);
        app()->setLocale($lang);

        $ad = Ad::query()->live()->with(['photos', 'city', 'category'])->find($id);
        abort_if($ad === null, 404);

        $current = ShareImage::version($ad);
        if ($version !== $current) {
            return redirect(route('ad.share_image', ['id' => $id, 'version' => $current, 'lang' => $lang]), 301);
        }

        $jpeg = ShareImage::get($ad, $current);
        abort_if($jpeg === null, 404);

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800, immutable', // the address changes when the picture does
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The address for this ad's picture in the current language (null while the switch is OFF or the ad is not live). */
    public static function urlFor(Ad $ad): ?string
    {
        if (! feature('share.image') || ! $ad->isLive()) {
            return null;
        }

        return route('ad.share_image', ['id' => $ad->id, 'version' => ShareImage::version($ad), 'lang' => app()->getLocale()]);
    }
}
