<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TontineContribution extends Model
{
    protected $fillable = [
        'tontine_cycle_id', 'amount_due', 'amount_paid', 'paid_date', 'status',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_date' => 'date',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TontineCycle::class, 'tontine_cycle_id');
    }

    public function markAsPaid(): void
    {
        $this->update([
            'amount_paid' => $this->amount_due,
            'paid_date' => now(),
            'status' => 'paid',
        ]);
    }
}