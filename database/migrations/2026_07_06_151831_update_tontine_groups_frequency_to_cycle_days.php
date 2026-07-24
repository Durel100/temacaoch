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
        Schema::table('tontine_groups', function (Blueprint $table) {
            // Ajoute la nouvelle colonne
            $table->unsignedInteger('cycle_days')->default(30)->after('frequency');
        });

        // Migrer les données existantes
        \DB::table('tontine_groups')->where('frequency', 'weekly')->update(['cycle_days' => 7]);
        \DB::table('tontine_groups')->where('frequency', 'monthly')->update(['cycle_days' => 30]);

        Schema::table('tontine_groups', function (Blueprint $table) {
            // Rendre frequency nullable (on garde pour compatibilité, mais cycle_days devient la référence)
            $table->string('frequency')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tontine_groups', function (Blueprint $table) {
            $table->dropColumn('cycle_days');
            $table->string('frequency')->nullable(false)->change();
        });
    }
};
