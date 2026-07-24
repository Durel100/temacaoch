<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $fillable = [
        'user_id', 'rule_id', 'priority', 'message',
        'is_read', 'was_notified', 'triggered_at', 'expires_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'was_notified' => 'boolean',
        'triggered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}