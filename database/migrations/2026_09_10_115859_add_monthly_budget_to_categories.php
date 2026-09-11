<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Budget mensuel optionnel pour une catégorie de dépense.
            // null = pas de budget suivi (comportement actuel).
            $table->decimal('monthly_budget', 12, 2)->nullable()->after('default_direction');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('monthly_budget');
        });
    }
};