<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\FixedCharge;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'quick_action_id', 'category_id', 'amount',
        'direction', 'source', 'transacted_at', 'note','fixed_charge_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transacted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function quickAction(): BelongsTo
    {
        return $this->belongsTo(QuickAction::class);
    }

    public function fixedCharge(): BelongsTo
    {
        return $this->belongsTo(FixedCharge::class);
    }
}