<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'round_number', 'winners_count', 'status', 'spin_plan', 'started_at', 'completed_at'])]
class GameRound extends Model
{
    protected $hidden = ['spin_plan'];

    protected function casts(): array
    {
        return ['spin_plan' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function spins(): HasMany
    {
        return $this->hasMany(GameSpin::class, 'game_round_id')->orderBy('spin_number');
    }
}
