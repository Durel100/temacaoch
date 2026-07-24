<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dependent extends Model
{
    protected $fillable = [
        'user_id', 'relation', 'age_range', 'is_schooled',
        'allowance_amount', 'allowance_frequency', 'allowance_managed_by',
    ];

    protected $casts = [
        'is_schooled' => 'boolean',
        'allowance_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Calcule le coût mensuel de l'argent de poche pour ce dépendant
    public function getMonthlyAllowanceCostAttribute(): float
    {
        if (!$this->allowance_amount) {
            return 0;
        }

        $multiplier = match ($this->allowance_frequency) {
            'daily' => 22,    // jours d'école approx.
            'weekly' => 4.33, // semaines par mois
            'monthly' => 1,
            default => 0,
        };

        return $this->allowance_amount * $multiplier;
    }
}