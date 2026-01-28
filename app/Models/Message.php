<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'message_type',
        'body',
        'meta',
        'is_deleted',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_deleted' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($message) {
            if (!$message->created_at) {
                $message->created_at = now();
            }
        });
    }

    /**
     * Get the conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender user.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Get the read receipts.
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Check if message is read by user.
     */
    public function isReadBy(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * Check if message is a system message.
     */
    public function isSystem(): bool
    {
        return $this->message_type === 'system';
    }

    /**
     * Mark message as read by user.
     */
    public function markAsReadBy(int $userId): void
    {
        if (!$this->isReadBy($userId)) {
            MessageRead::create([
                'message_id' => $this->id,
                'user_id' => $userId,
                'read_at' => now(),
            ]);
        }
    }
}
