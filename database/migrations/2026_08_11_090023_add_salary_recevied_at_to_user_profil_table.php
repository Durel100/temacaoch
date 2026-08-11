<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Bug 2 : date de confirmation de la paie du cycle en cours.
            // Posée à l'onboarding « Oui, déjà reçu » et par le bouton
            // « J'ai reçu mon salaire ». null = paie non confirmée.
            $table->timestamp('salary_received_at')->nullable()->after('remaining_snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('salary_received_at');
        });
    }
};