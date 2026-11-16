<?php

namespace Database\Seeders\Concerns;

/**
 * Seeders give names in every language we have, but only ADD what is missing:
 * a name an admin already changed is never overwritten.
 */
trait TranslatesNames
{
    private ?array $somali = null;

    /** @param  array<string,string>|null  $existing */
    protected function names(?array $existing, string $english): array
    {
        $this->somali ??= require database_path('seeders/data/somali.php');
        $fresh = array_filter(['en' => $english, 'so' => $this->somali[$english] ?? null]);

        return ($existing ?? []) + $fresh;
    }
}
