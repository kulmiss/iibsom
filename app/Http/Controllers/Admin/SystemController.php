<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Demo\DemoData;
use App\Support\Health\HealthChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** System health + demo data controls. */
class SystemController extends Controller
{
    public function health(HealthChecker $health): JsonResponse
    {
        $queueWaiting = rescue(fn () => config('queue.default') === 'database' ? DB::table('jobs')->count() : null, null, false);

        return response()->json([
            'checks' => $health->run(),
            'facts' => [
                ['label' => 'Hosting mode', 'value' => config('iibsom.hosting') === 'shared' ? 'Shared hosting' : 'VPS'],
                ['label' => 'Environment', 'value' => app()->environment()],
                ['label' => 'PHP', 'value' => PHP_VERSION],
                ['label' => 'Laravel', 'value' => app()->version()],
                ['label' => 'Database', 'value' => DB::connection()->getDriverName().' '.rescue(fn () => DB::scalar('select version()'), '?', false)],
                ['label' => 'Cache / sessions / queue', 'value' => config('cache.default').' / '.config('session.driver').' / '.config('queue.default')],
                ['label' => 'Jobs waiting', 'value' => $queueWaiting === null ? 'n/a' : (string) $queueWaiting],
                ['label' => 'Photos stored on', 'value' => config('iibsom.photos_disk')],
                ['label' => 'Email sender', 'value' => config('mail.default')],
                ['label' => 'Google sign-in keys', 'value' => config('services.google.client_id') ? 'set' : 'MISSING'],
            ],
        ]);
    }

    public function demo(): JsonResponse
    {
        return response()->json([
            'show' => feature('site.demo_data'),
            'counts' => DemoData::counts(),
        ]);
    }

    /** "Delete all demo data" before going live. Needs the word DELETE typed. */
    public function deleteDemo(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.in' => 'Type DELETE to confirm.']);

        try {
            $counts = DemoData::deleteAll($request->user()->id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not delete demo data. Nothing was changed.'], 500);
        }

        Activity::log('demo.deleted', "Deleted all demo data ({$counts['ads']} ads, {$counts['users']} users, {$counts['reports']} reports)", null, $counts);

        return response()->json(['deleted' => $counts]);
    }
}
