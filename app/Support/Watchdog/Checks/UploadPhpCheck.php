<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\File;

/** The uploads folders must only hold photos: any PHP (or .htaccess) file there is urgent. */
final class UploadPhpCheck implements Check
{
    private const BAD = '/\.(php\d?|phtml|phar|pht|phps|cgi|pl|htaccess)$/i';

    public function key(): string
    {
        return 'security.upload_php';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $bad = [];
        foreach (config('watchdog.upload_folders') as $relative) {
            $path = base_path($relative);
            if (! is_dir($path)) {
                continue;
            }
            foreach (File::allFiles($path, true) as $file) {
                if (preg_match(self::BAD, $file->getFilename()) === 1) {
                    $bad[] = $relative.'/'.str_replace('\\', '/', $file->getRelativePathname());
                }
            }
        }
        if ($bad === []) {
            return [];
        }
        sort($bad);
        $count = count($bad);

        return [new Finding($this->key(), 'urgent', "{$count} unexpected program file(s) in the uploads folder",
            'Found: '.implode(', ', array_slice($bad, 0, 5)).($count > 5 ? ' and '.($count - 5).' more' : '').'. Uploads must only hold photos; a program file there can be used to take over the site.',
            ['Do not open the file in a browser.', 'Ask your developer or host to look at it and delete it.', 'Change the passwords of the server and the database.'],
            ['files' => $count])];
    }
}
