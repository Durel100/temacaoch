<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('score');
            $table->enum('status', ['stable', 'to_watch', 'fragile']);
            $table->date('calculated_for_month');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_health_scores');
    }
};