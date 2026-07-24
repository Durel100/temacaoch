<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontine_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_cycle_id')->constrained()->onDelete('cascade');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['paid', 'pending', 'late'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontine_contributions');
    }
};