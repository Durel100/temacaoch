<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tontine_groups', function (Blueprint $table) {
            $table->string('frequency_type')->default('days')->after('cycle_days');
            $table->integer('cycle_months')->nullable()->after('frequency_type');
        });
    }
    public function down(): void {
        Schema::table('tontine_groups', function (Blueprint $table) {
            $table->dropColumn(['frequency_type', 'cycle_months']);
        });
    }
};