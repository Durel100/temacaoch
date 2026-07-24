<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'marital_status',
        'employment_type',
        'spouse_contributes',
        'spouse_monthly_contribution',
        'shared_fixed_charges',
        'spending_tendency',
        'budget_struggle_frequency',
        'budget_preference',
        'behavior_profile_calculated',
        'behavior_profile_updated_at',
        'salary_day',
        'current_month_remaining',
        'remaining_fixed_charges_this_month',
        'remaining_snapshot_date',
    ];

    protected $casts = [
        'behavior_profile_updated_at'         => 'datetime',
        'remaining_snapshot_date'             => 'date',
        'current_month_remaining'             => 'decimal:2',
        'remaining_fixed_charges_this_month'  => 'decimal:2',
        'spouse_monthly_contribution'         => 'decimal:2',
        'spouse_contributes'                  => 'boolean',
        'shared_fixed_charges'                => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}