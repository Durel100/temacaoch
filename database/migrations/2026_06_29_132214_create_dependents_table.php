<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('relation', ['child', 'parent', 'other']);
            $table->enum('age_range', ['0-5', '6-12', '13-18', 'adult'])->nullable();
            $table->boolean('is_schooled')->default(false);
            $table->decimal('allowance_amount', 10, 2)->nullable();
            $table->enum('allowance_frequency', ['daily', 'weekly', 'monthly'])->nullable();
            $table->enum('allowance_managed_by', ['parent', 'child'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependents');
    }
};