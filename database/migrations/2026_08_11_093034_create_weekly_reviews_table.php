<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');                 // lundi de la semaine couverte
            $table->json('payload');                    // bilan structuré (resume, alertes, conseils, opportunites...)
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);  // un seul bilan par semaine et par utilisateur
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reviews');
    }
};