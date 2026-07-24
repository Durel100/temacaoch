<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('marital_status', ['single', 'married', 'in_relationship', 'divorced', 'widowed']);
            $table->enum('spending_tendency', ['spends_quickly', 'saves', 'depends'])->nullable();
            $table->enum('budget_struggle_frequency', ['often', 'sometimes', 'rarely'])->nullable();
            $table->enum('budget_preference', ['strict', 'flexible'])->nullable();
            $table->string('behavior_profile_calculated')->nullable();
            $table->timestamp('behavior_profile_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};