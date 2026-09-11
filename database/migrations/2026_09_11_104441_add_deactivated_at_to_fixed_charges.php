<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_charges', function (Blueprint $table) {
            // Date d'archivage d'une charge → permet de la réactiver automatiquement
            // au nouveau cycle. null quand la charge est active.
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_charges', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};