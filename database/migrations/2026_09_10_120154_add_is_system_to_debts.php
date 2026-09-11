<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            // Marque une dette générée automatiquement (ex : découvert budget).
            // Stable au renommage → sert à retrouver la dette système sans dépendre du label.
            $table->boolean('is_system')->default(false)->after('label');
        });

        // Les découverts déjà créés par le système deviennent is_system = true.
        DB::table('debts')->where('label', 'Découvert budget')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};