<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['started_by', 'name', 'status', 'participant_count', 'rounds_config', 'priority_carry_forward', 'started_at', 'completed_at'])]
class Game extends Model
{
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return ['rounds_config' => 'array', 'priority_carry_forward' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class)->orderBy('round_number');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GameParticipant::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
