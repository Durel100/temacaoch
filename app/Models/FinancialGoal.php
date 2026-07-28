<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialGoal extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'target_amount',
        'current_amount',
        'target_date',
        'category_id',        // ← catégorie liée pour les transactions
        'is_archived',        // ← archivé quand objectif atteint
        'archived_at',        // ← date d'archivage
        'last_estimated_at',  // ← date de la dernière estimation IA
    ];

    protected $casts = [
        'target_amount'      => 'decimal:2',
        'current_amount'     => 'decimal:2',
        'target_date'        => 'date',
        'is_archived'        => 'boolean',
        'archived_at'        => 'datetime',
        'last_estimated_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount == 0) return 0;
        return round(($this->current_amount / $this->target_amount) * 100, 1);
    }

    public function isEstimationLocked(): bool
    {
        if (!$this->last_estimated_at) return false;
        // Verrouillé 1 mois, déverrouillé après 2 mois si pas encore atteint
        return $this->last_estimated_at->diffInMonths(now()) < 2;
    }

    public function canEstimate(): bool
    {
        if ($this->is_archived) return false;
        return !$this->isEstimationLocked();
    }

    // Archive automatiquement si 100% atteint
    public function checkAndAutoArchive(): void
    {
        if (!$this->is_archived && $this->progress_percent >= 100) {
            $this->update([
                'is_archived' => true,
                'archived_at' => now(),
                'current_amount' => $this->target_amount,
            ]);
        }
    }
}