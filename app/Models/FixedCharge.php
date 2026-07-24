<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedCharge extends Model
{
    protected $fillable = ['user_id', 'label', 'amount', 'frequency', 'is_active'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Normalise toute charge en équivalent mensuel, peu importe sa fréquence
    public function getMonthlyEquivalentAttribute(): float
    {
        return match ($this->frequency) {
            'weekly' => $this->amount * 4.33,
            'yearly' => $this->amount / 12,
            default => (float) $this->amount, // monthly
        };
    }
}