<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\GameSpin;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameService
{
    public function create(User $user, string $name): Game
    {
        $eligibleCount = Participant::query()->where('is_active', true)->count();
        $rounds = config('game.rounds');
        if ($eligibleCount < ($rounds[0] ?? 1)) {
            throw new \DomainException('Add enough active participants for the first round before starting a game.');
        }

        return DB::transaction(function () use ($user, $name, $eligibleCount, $rounds): Game {
            $game = Game::query()->create([
                'started_by' => $user->id,
                'name' => $name,
                'status' => 'draft',
                'participant_count' => $eligibleCount,
                'rounds_config' => $rounds,
                'priority_carry_forward' => config('game.priority_carry_forward'),
            ]);

            Participant::query()->where('is_active', true)->orderBy('id')->chunkById(500, function ($participants) use ($game): void {
                $game->participants()->insert($participants->map(fn (Participant $participant): array => [
                    'game_id' => $game->id,
                    'participant_id' => $participant->id,
                    'name' => $participant->name,
                    'username' => $participant->username,
                    'priority' => $participant->priority,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });

            app(RoundService::class)->start($game, 1);

            return $game->fresh('rounds');
        });
    }

    public function spin(Game $game): GameSpin
    {
        return DB::transaction(function () use ($game): GameSpin {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if ($lockedGame->status !== 'in_progress') {
                throw new \DomainException('Only an active game can be spun.');
            }
            $round = $lockedGame->rounds()->where('status', 'active')->lockForUpdate()->firstOrFail();
            $previousSpin = $round->spins()->latest('spin_number')->first();
            if ($previousSpin && $previousSpin->spun_at->copy()->addMilliseconds(config('game.spin_duration_ms') + 500)->isFuture()) {
                throw new \DomainException('Let the current spin finish before starting another.');
            }
            $nextNumber = (int) $round->spins()->max('spin_number') + 1;
            $planned = $round->spin_plan[$nextNumber - 1] ?? null;
            if (! $planned) {
                throw new \DomainException('The round has no remaining spins.');
            }

            $pool = app(RoundService::class)->poolForRound($lockedGame, $round->round_number);
            $candidateId = (int) ($planned['participant_id'] ?? 0);
            $snapshotWinner = $pool->firstWhere('participant_id', $candidateId);
            if (! $snapshotWinner) {
                throw new \DomainException('The planned player is not eligible for this round.');
            }
            if ($round->spins()->where('participant_id', $candidateId)->exists()) {
                throw new \DomainException('This player has already been selected in this round.');
            }

            $alreadySelected = [...$round->spins()->pluck('participant_id')->all(), $candidateId];
            $segmentNames = $pool->reject(fn (GameParticipant $entry): bool => in_array((int) $entry->participant_id, $alreadySelected, true))
                ->shuffle()
                ->take(max(0, config('game.wheel_visible_segments') - 1))
                ->map(fn (GameParticipant $entry): array => ['name' => $entry->name, 'username' => $entry->username])
                ->all();
            $segments = [...$segmentNames, ['name' => $snapshotWinner->name, 'username' => $snapshotWinner->username, 'winner' => true]];
            for ($index = count($segments) - 1; $index > 0; $index--) {
                $swap = random_int(0, $index);
                [$segments[$index], $segments[$swap]] = [$segments[$swap], $segments[$index]];
            }

            $spin = GameSpin::query()->create([
                'game_round_id' => $round->id,
                'spin_number' => $nextNumber,
                'participant_id' => $candidateId,
                'was_priority' => $planned['was_priority'],
                'meta' => ['plan_id' => (string) Str::ulid(), 'winner_name' => $snapshotWinner->name, 'winner_username' => $snapshotWinner->username, 'segments' => $segments],
                'spun_at' => now(),
            ]);

            if ($nextNumber === $round->winners_count) {
                $round->update(['status' => 'completed', 'completed_at' => now()]);
                if ($round->round_number === count($lockedGame->rounds_config)) {
                    $lockedGame->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }

            return $spin;
        });
    }
}
