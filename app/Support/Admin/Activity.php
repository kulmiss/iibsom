<?php

namespace App\Support\Admin;

use App\Models\AdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** Write one line to the admin activity log (and the security log). */
final class Activity
{
    /**
     * A line written by the site itself (no staff member, no IP), e.g. an ad hidden after reports (PR 89).
     *
     * @param  array<string, mixed>  $details
     */
    public static function system(string $action, string $summary, ?Model $subject = null, array $details = []): AdminActivity
    {
        $entry = AdminActivity::create([
            'user_id' => null,
            'action' => $action,
            'subject_type' => $subject ? strtolower(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 300),
            'details' => $details ?: null,
            'ip' => null,
        ]);
        Log::channel('security')->info('system.'.$action, ['subject' => $entry->subject_type.':'.$entry->subject_id]);

        return $entry;
    }

    /** @param  array<string, mixed>  $details */
    public static function log(string $action, string $summary, ?Model $subject = null, array $details = []): AdminActivity
    {
        $user = auth()->user();

        $entry = AdminActivity::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? strtolower(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 300),
            'details' => $details ?: null,
            'ip' => request()->ip(), // staff are always signed in
        ]);

        Log::channel('security')->info('admin.'.$action, ['by' => $user?->id, 'subject' => $entry->subject_type.':'.$entry->subject_id]);

        return $entry;
    }
}
