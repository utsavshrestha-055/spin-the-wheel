<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_id', 'participant_id', 'name', 'username', 'priority'])]
class GameParticipant extends Model
{
    protected function casts(): array
    {
        return ['priority' => 'integer'];
    }
}
