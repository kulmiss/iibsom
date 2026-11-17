<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Conversation $conversation
 * @property-read User $sender
 */
class Message extends Model
{
    public const UPDATED_AT = null;

    public const MAX_LENGTH = 2000;

    protected $fillable = ['sender_id', 'body'];

    /** Clock time in Somalia (times are stored in UTC). */
    public function clock(): string
    {
        return $this->created_at->copy()->timezone('Africa/Mogadishu')->format('H:i');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
