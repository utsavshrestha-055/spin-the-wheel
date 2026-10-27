<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_round_id', 'spin_number', 'participant_id', 'was_priority', 'meta', 'spun_at'])]
class GameSpin extends Model
{
    protected function casts(): array
    {
        return ['was_priority' => 'boolean', 'meta' => 'array', 'spun_at' => 'datetime'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
