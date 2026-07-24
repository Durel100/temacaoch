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
            $table->boolean('spouse_contributes')->nullable()->after('marital_status');
            $table->decimal('spouse_monthly_contribution', 12, 2)->nullable()->after('spouse_contributes');
            $table->boolean('shared_fixed_charges')->nullable()->after('spouse_monthly_contribution');
        });
    }
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'spouse_contributes',
                'spouse_monthly_contribution',
                'shared_fixed_charges',
            ]);
        });
    }
};
