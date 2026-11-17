<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Support\Privacy\Bots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Counts a banner click (a number per day, nothing about the visitor), then goes to the link. */
class BannerClickController extends Controller
{
    public function __invoke(Request $request, Banner $banner): RedirectResponse
    {
        $target = $banner->target();
        abort_if($target === null, 404);
        if ($banner->placement !== 'slider' && ! feature('banner_ads')) {
            abort(404);
        }

        if (! Bots::is($request->userAgent()) && Banner::active()->whereKey($banner->id)->exists()) {
            DB::table('banner_clicks')->upsert(
                [['banner_id' => $banner->id, 'day' => Carbon::now('Africa/Mogadishu')->toDateString(), 'clicks' => 1]],
                ['banner_id', 'day'],
                ['clicks' => DB::raw('`clicks` + 1')],
            );
            DB::table('banners')->where('id', $banner->id)->increment('clicks');
        }

        return redirect()->away($target)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
