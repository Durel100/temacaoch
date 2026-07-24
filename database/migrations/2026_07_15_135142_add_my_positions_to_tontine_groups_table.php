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
            // Remplace my_position (int) par my_positions (json)
            $table->json('my_positions')->nullable()->after('my_position');
        });
    }

    public function down(): void
    {
        Schema::table('tontine_groups', function (Blueprint $table) {
            $table->dropColumn('my_positions');
        });
    }
};
