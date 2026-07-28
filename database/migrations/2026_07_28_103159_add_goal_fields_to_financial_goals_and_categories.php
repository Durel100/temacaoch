<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('target_date');
            $table->boolean('is_archived')->default(false)->after('category_id');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->timestamp('last_estimated_at')->nullable()->after('archived_at');

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        // Ajouter goal_id sur categories pour la relation inverse
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('goal_id')->nullable()->after('user_id');
            $table->foreign('goal_id')->references('id')->on('financial_goals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropColumn('goal_id');
        });
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'is_archived', 'archived_at', 'last_estimated_at']);
        });
    }
};