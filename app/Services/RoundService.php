<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\GameRound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoundService
{
    public function start(Game $game, int $roundNumber): GameRound
    {
        if ($roundNumber < 1 || $roundNumber > count($game->rounds_config)) {
            throw new \DomainException('That round does not exist.');
        }

        return DB::transaction(function () use ($game, $roundNumber): GameRound {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (($roundNumber === 1 && $game->status !== 'draft') || ($roundNumber > 1 && $game->status !== 'in_progress')) {
                throw new \DomainException('The game is not ready to start this round.');
            }
            if ($game->rounds()->where('round_number', $roundNumber)->exists()) {
                throw new \DomainException('This round has already been started.');
            }

            $pool = $this->poolForRound($game, $roundNumber);

            $winnersCount = $game->rounds_config[$roundNumber - 1];
            if ($winnersCount > $pool->count()) {
                throw new \DomainException('This round needs more eligible players than the previous round selected.');
            }

            $plan = app(SpinPlanner::class)->plan($pool, $winnersCount, $roundNumber === 1 || $game->priority_carry_forward);
            $round = $game->rounds()->create([
                'round_number' => $roundNumber,
                'winners_count' => $winnersCount,
                'status' => 'active',
                'spin_plan' => $plan,
                'started_at' => now(),
            ]);

            if ($roundNumber === 1) {
                $game->update(['status' => 'in_progress', 'started_at' => now()]);
            }

            return $round;
        });
    }

    /** @return Collection<int, GameParticipant> */
    public function poolForRound(Game $game, int $roundNumber): Collection
    {
        $query = $game->participants();

        if ($roundNumber > 1) {
            $previous = GameRound::query()
                ->where('game_id', $game->id)
                ->where('round_number', $roundNumber - 1)
                ->first();

            if (! $previous || $previous->status !== 'completed') {
                throw new \DomainException('Complete the previous round first.');
            }

            $query->whereIn('participant_id', $previous->spins()->pluck('participant_id'));
        }

        return $query->orderBy('participant_id')->get();
    }
}
