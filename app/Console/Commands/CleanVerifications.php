<?php

namespace App\Console\Commands;

use App\Models\VerificationRequest;
use App\Support\Files\PrivateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ID photos are kept no longer than needed (PR 107). Every night:
 * 1. requests nobody checked in 30 days end ("expired") and their photo is deleted;
 * 2. a decided request that still has a photo (should never happen) loses it;
 * 3. a photo file with no request (for example after a crash) is deleted.
 * Only numbers are logged, never names or files.
 */
class CleanVerifications extends Command
{
    protected $signature = 'iibsom:verifications:clean';

    protected $description = 'Delete ID photos that are no longer needed';

    public function handle(): int
    {
        $expired = 0;
        VerificationRequest::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(VerificationRequest::KEEP_PENDING_DAYS))
            ->each(function (VerificationRequest $v) use (&$expired): void {
                $v->deletePhoto();
                $v->forceFill(['status' => 'expired', 'reviewed_at' => now()])->save();
                $expired++;
            });

        $leftover = 0;
        VerificationRequest::where('status', '!=', 'pending')->whereNotNull('file_path')
            ->each(function (VerificationRequest $v) use (&$leftover): void {
                $v->deletePhoto();
                $v->save();
                $leftover++;
            });

        $known = VerificationRequest::whereNotNull('file_path')->pluck('file_path')->flip();
        $orphans = 0;
        foreach (Storage::disk(PrivateFiles::DISK)->files(VerificationRequest::FOLDER) as $file) {
            if (! $known->has($file)) {
                PrivateFiles::delete($file);
                $orphans++;
            }
        }

        Log::info('verifications.clean', ['expired' => $expired, 'leftover' => $leftover, 'orphans' => $orphans]);
        $this->info("Expired: {$expired}. Leftover photos removed: {$leftover}. Files without a request removed: {$orphans}.");

        return self::SUCCESS;
    }
}
