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
        Schema::create('game_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_round_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('spin_number');
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->boolean('was_priority')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('spun_at');
            $table->timestamps();
            $table->unique(['game_round_id', 'spin_number']);
            $table->unique(['game_round_id', 'participant_id']);
            $table->index(['participant_id', 'spun_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_spins');
    }
};
