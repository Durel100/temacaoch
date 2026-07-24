<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Jour du mois où le salaire tombe (1-31)
            $table->unsignedTinyInteger('salary_day')->nullable()->after('budget_preference');
            // Montant restant si la date est déjà passée (saisi à l'onboarding)
            $table->decimal('current_month_remaining', 12, 2)->nullable()->after('salary_day');
            // Charges fixes encore à régler ce mois (saisi à l'onboarding)
            $table->decimal('remaining_fixed_charges_this_month', 12, 2)->nullable()->after('current_month_remaining');
            // Date à laquelle ces données ont été saisies (pour savoir si elles sont encore valides)
            $table->date('remaining_snapshot_date')->nullable()->after('remaining_fixed_charges_this_month');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'salary_day',
                'current_month_remaining',
                'remaining_fixed_charges_this_month',
                'remaining_snapshot_date',
            ]);
        });
    }
};
