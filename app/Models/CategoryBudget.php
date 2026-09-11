<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryBudget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'monthly_budget',
    ];

    protected $casts = [
        'monthly_budget' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}