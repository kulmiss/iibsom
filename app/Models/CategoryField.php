<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryField extends Model
{
    public const TYPES = ['text', 'number', 'select', 'boolean'];

    protected $guarded = ['id'];

    protected $casts = [
        'label' => 'array',
        'options' => 'array',
        'required' => 'boolean',
        'filterable' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getLabelTextAttribute(): string
    {
        return translated($this->label);
    }

    /** @return array<string, string> value => label in the current language */
    public function optionList(): array
    {
        return collect($this->options ?? [])
            ->mapWithKeys(fn (array $o) => [$o['value'] => translated($o['label'] ?? $o['value'])])
            ->all();
    }
}
