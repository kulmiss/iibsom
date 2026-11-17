<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\View\View;

/** Public shop page (switch "shops"): only while the shop is active. */
class ShopPageController extends Controller
{
    public function show(string $shop): View
    {
        $found = Shop::with(['user.city', 'city'])->where('slug', $shop)->first();
        abort_unless($found !== null && $found->isActive(), 404);

        // Same rules as every list: live, not ended, demo ads only while demo data is ON.
        $ads = $found->user->ads()->live()->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->with(['category:id,name,slug,icon,parent_id', 'city:id,name,slug', 'cover'])
            ->latest('published_at')->paginate(24);

        return view('shops.show', ['shop' => $found, 'ads' => $ads]);
    }
}
