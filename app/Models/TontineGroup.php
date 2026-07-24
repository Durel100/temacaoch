<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TontineGroup extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'contribution_amount',
        'cycle_days',
        'cycle_months',
        'frequency_type',
        'total_members',
        'my_position',
        'my_positions',
        'start_date',
        'is_active',
    ];

    protected $casts = [
        'my_positions'        => 'array',
        'start_date'          => 'date',
        'is_active'           => 'boolean',
        'contribution_amount' => 'decimal:2',
    ];

    // ─── Relations ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(TontineCycle::class, 'tontine_group_id');
    }

    // ─── Méthodes métier ─────────────────────────────────────────────

    /**
     * Génère automatiquement tous les cycles à la création
     */
    public function generateCycles(): void
    {
        $currentDate = Carbon::parse($this->start_date);
        $myPositions = $this->my_positions ?? [$this->my_position];
        $useMonths   = $this->frequency_type === 'months' && $this->cycle_months;

        for ($i = 1; $i <= $this->total_members; $i++) {
            $this->cycles()->create([
                'cycle_number'   => $i,
                'scheduled_date' => $currentDate->copy(),
                'is_my_turn'     => in_array($i, $myPositions),
                'status'         => 'upcoming',
            ]);

            // Ajouter des mois exacts ou des jours selon le type
            if ($useMonths) {
                $currentDate->addMonths($this->cycle_months);
            } else {
                $currentDate->addDays($this->cycle_days);
            }
        }
    }

    /**
     * Tous les cycles où c'est mon tour de recevoir
     */
    public function myPayoutCycles(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->cycles()
            ->where('is_my_turn', true)
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Prochain cycle où je reçois (le plus proche dans le futur)
     */
    public function myPayoutCycle(): ?TontineCycle
    {
        return $this->cycles()
            ->where('is_my_turn', true)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Prochain cycle à payer (le plus proche non encore payé)
     */
    public function nextCycle(): ?TontineCycle
    {
        return $this->cycles()
            ->where('scheduled_date', '>=', now()->toDateString())
            ->where('status', 'upcoming')
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Montant total que je recevrai sur toute la durée de la tontine
     * = une réception × nombre de mes positions
     */
    public function myTotalPayout(): float
    {
        $myPositionsCount = count($this->my_positions ?? [$this->my_position]);
        return $this->contribution_amount * $this->total_members * $myPositionsCount;
    }

    /**
     * Montant reçu à chaque réception
     */
    public function payoutAmount(): float
    {
        return $this->contribution_amount * $this->total_members;
    }
}