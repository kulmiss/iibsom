<?php

namespace App\Models\Concerns;

/**
 * "name" is stored as JSON: {"en": "Cars", "so": "Baabuur"}.
 * $model->label gives the text in the current language (falls back to English).
 */
trait HasTranslatedName
{
    public function initializeHasTranslatedName(): void
    {
        $this->mergeCasts(['name' => 'array']);
    }

    public function getLabelAttribute(): string
    {
        return translated($this->name);
    }
}
