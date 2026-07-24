<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['user_id', 'name', 'icon', 'default_direction', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function quickActions(): HasMany
    {
        return $this->hasMany(QuickAction::class);
    }

    // Récupère les catégories système + celles propres à un utilisateur
    public static function availableFor(int $userId)
    {
        return static::where('is_system', true)
            ->orWhere('user_id', $userId)
            ->get();
    }
}