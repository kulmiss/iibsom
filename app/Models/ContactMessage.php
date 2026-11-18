<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message from the contact form (switch "site.contact_form"). */
class ContactMessage extends Model
{
    public const UPDATED_AT = null;

    public const TOPICS = ['ad', 'account', 'problem', 'business', 'other'];

    public const KEEP_DAYS = 180;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
