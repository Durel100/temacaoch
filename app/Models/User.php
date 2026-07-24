<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmailNotification;

class User extends Authenticatable implements MustVerifyEmail
{
     use HasFactory, Notifiable;
     
    protected $fillable = ['name', 'email', 'phone', 'locale', 'is_admin', 'password', 'onboarding_completed_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
    'email_verified_at' => 'datetime',
    'onboarding_completed_at' => 'datetime', 
    'password' => 'hashed',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Dependent::class);
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    public function incomeRecords(): HasMany
    {
        return $this->hasMany(IncomeRecord::class);
    }

    public function fixedCharges(): HasMany
    {
        return $this->hasMany(FixedCharge::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function quickActions(): HasMany
    {
        return $this->hasMany(QuickAction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tontineGroups(): HasMany
    {
        return $this->hasMany(TontineGroup::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function financialHealthScores(): HasMany
    {
        return $this->hasMany(FinancialHealthScore::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}