<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReview extends Model
{
    protected $fillable = [
        'user_id',
        'week_start',
        'payload',
        'generated_at',
    ];

    protected $casts = [
        'week_start'   => 'date',
        'payload'      => 'array',
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}