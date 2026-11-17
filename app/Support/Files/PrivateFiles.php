<?php

namespace App\Support\Files;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Sensitive uploads (ID photos, payment receipts): encrypted with the app key
 * and kept on the private "local" disk (storage/app/private), never public.
 */
class PrivateFiles
{
    public const DISK = 'local';

    public static function put(string $folder, string $bytes): string
    {
        $path = trim($folder, '/').'/'.bin2hex(random_bytes(16)).'.bin';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString(base64_encode($bytes)));

        return $path;
    }

    public static function get(?string $path): ?string
    {
        if ($path === null || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return base64_decode(Crypt::decryptString((string) Storage::disk(self::DISK)->get($path)), true) ?: null;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
