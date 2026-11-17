<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The seller's own shop page settings (switch "shops"). */
class ShopController extends Controller
{
    public function edit(Request $request, Catalog $catalog): View
    {
        $user = $this->authUser($request);

        return view('account.shop', [
            'shop' => $user->shop()->first() ?? new Shop(['city_id' => $user->city_id]),
            'cities' => $catalog->cities(),
        ]);
    }

    public function save(Request $request, Catalog $catalog): RedirectResponse
    {
        $user = $this->authUser($request);
        $shop = $user->shop()->first() ?? new Shop; // fresh: never a stale cached relation

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('shops', 'slug')->ignore($shop->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'hours' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer', Rule::in(array_column($catalog->cities(), 'id'))],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:min_width=128,min_height=128'],
        ], [
            'slug.regex' => __('account.shop.slug_rule'),
            'slug.unique' => __('account.shop.slug_taken'),
        ], [
            'name' => __('account.shop.name'),
            'slug' => __('account.shop.slug'),
            'description' => __('account.shop.description'),
            'hours' => __('account.shop.hours'),
            'logo' => __('account.shop.logo'),
        ]);

        $shop->fill([
            'name' => strip_tags($data['name']),
            'slug' => $data['slug'],
            'description' => isset($data['description']) ? strip_tags($data['description']) : null,
            'hours' => isset($data['hours']) ? strip_tags($data['hours']) : null,
            'city_id' => $data['city_id'] ?? null,
        ]);
        if ($request->hasFile('logo')) {
            $shop->saveLogo($request->file('logo'));
        }
        $user->shop()->save($shop);

        return redirect(lroute('account.shop'))->with('status', __('account.shop.saved'));
    }

    /** Suggestion for the web address field (from the shop name). */
    public static function suggestSlug(string $name): string
    {
        return Str::limit(Str::slug($name), 40, '');
    }
}
