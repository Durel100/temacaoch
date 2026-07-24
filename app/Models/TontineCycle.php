<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TontineCycle extends Model
{
    protected $fillable = [
        'tontine_group_id', 'cycle_number', 'scheduled_date',
        'is_my_turn', 'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'is_my_turn' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TontineGroup::class, 'tontine_group_id');
    }

    public function contribution(): HasOne
    {
        return $this->hasOne(TontineContribution::class);
    }

    public function getPayoutAmountAttribute(): float
    {
        if (!$this->is_my_turn) {
            return 0;
        }
        return $this->group->contribution_amount * $this->group->total_members;
    }

    
}