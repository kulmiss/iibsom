<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → Appearance: the admin brand (accent) colour. */
class AppearanceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['brand_color' => setting('admin.brand_color')]);
    }

    public function update(Request $request, SettingsManager $settings): JsonResponse
    {
        $color = $request->validate(['brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']])['brand_color'];
        $old = setting('admin.brand_color');
        $settings->set('admin.brand_color', strtolower($color), $this->authUser($request)->id);
        Activity::log('settings.brand_color', "Brand colour changed from {$old} to {$color}", null, ['old' => $old, 'new' => $color]);

        return response()->json(['brand_color' => strtolower($color)]);
    }
}
