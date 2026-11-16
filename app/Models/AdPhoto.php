<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdPhoto extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['variants' => 'array'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /** Public URL for a size ("thumb", "medium", "large") and format ("webp", "avif"). */
    public function url(string $size = 'medium', string $format = 'webp'): ?string
    {
        $path = $this->variants[$size][$format] ?? $this->variants[$size]['webp'] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : null;
    }
}
