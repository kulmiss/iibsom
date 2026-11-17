<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/** Settings → General. */
class SettingsController extends Controller
{
    public function show(GeneralSettings $general): JsonResponse
    {
        return response()->json($general->values());
    }

    public function update(Request $request, GeneralSettings $general): JsonResponse
    {
        $data = $request->validate(GeneralSettings::rules(), GeneralSettings::messages());
        if ($problem = GeneralSettings::paymentProblem($data)) {
            throw ValidationException::withMessages(['payments_test_mode' => $problem]);
        }

        $before = $general->values();
        $general->save($data, $this->authUser($request)->id);
        Activity::log('settings.general', 'Changed general settings', null, ['before' => $before]);

        return response()->json($general->values());
    }

    /** "Send a test now": last week's summary to the signed-in staff member only. */
    public function testWeeklySummary(Request $request): JsonResponse
    {
        if (! feature('reports.weekly_email')) {
            return response()->json(['message' => 'The "Weekly summary email" switch is OFF.'], 422);
        }
        $email = $this->authUser($request)->email;
        $code = Artisan::call('iibsom:weekly-summary', ['--to' => $email]);

        return $code === 0
            ? response()->json(['message' => "Sent to {$email}. Check your inbox (and spam)."])
            : response()->json(['message' => 'Could not send the email. Check the MAIL_ settings in .env (details in storage/logs).'], 502);
    }
}
