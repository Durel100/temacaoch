<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontine_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_group_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('cycle_number');
            $table->date('scheduled_date');
            $table->boolean('is_my_turn')->default(false);
            $table->enum('status', ['upcoming', 'completed', 'missed'])->default('upcoming');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontine_cycles');
    }
};