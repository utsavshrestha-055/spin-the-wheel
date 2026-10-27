<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Models\Game;
use App\Models\Participant;
use App\Services\GameService;
use App\Services\RoundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', Game::class);

        return Inertia::render('Games/Setup', ['rounds' => config('game.rounds'), 'participantCount' => Participant::where('is_active', true)->count()]);
    }

    public function store(StoreGameRequest $request, GameService $games): RedirectResponse
    {
        $this->authorize('create', Game::class);
        $data = $request->validated();
        try {
            $game = $games->create($request->user(), $data['name']);
        } catch (\DomainException $exception) {
            return back()->withErrors(['game' => $exception->getMessage()]);
        }

        return redirect()->route('games.show', $game)->with('success', 'Game started. The participant list is now locked to this snapshot.');
    }

    public function show(Game $game): Response
    {
        $this->authorize('view', $game);
        $state = $this->stateData($game);
        $game->unsetRelation('rounds');

        return Inertia::render('Games/Control', ['game' => $game, 'state' => $state]);
    }

    public function spin(Game $game, GameService $games): RedirectResponse
    {
        $this->authorize('update', $game);
        try {
            $spin = $games->spin($game);
        } catch (\DomainException $exception) {
            return back()->withErrors(['spin' => $exception->getMessage()]);
        }

        $result = [
            'spin' => ['number' => $spin->spin_number, 'winner' => ['name' => $spin->meta['winner_name'], 'username' => $spin->meta['winner_username']], 'wasPriority' => $spin->was_priority],
            'state' => $this->stateData($game->fresh()),
        ];

        return redirect()->route('games.show', $game)->with('spin', $result);
    }

    public function nextRound(Game $game, Request $request, RoundService $rounds): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $game);
        $roundNumber = (int) $request->validate(['round_number' => ['required', 'integer', 'min:2']])['round_number'];
        try {
            $rounds->start($game, $roundNumber);
        } catch (\DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        $state = $this->stateData($game->fresh());
        if ($request->expectsJson()) {
            return response()->json($state);
        }

        return redirect()->route('games.show', $game)
            ->with('success', 'Round '.$roundNumber.' is ready.')
            ->with('state', $state);
    }

    public function stage(Game $game): Response
    {
        $this->authorize('view', $game);

        return Inertia::render('Games/Stage', ['gameId' => $game->public_id, 'initialState' => $this->stateData($game)]);
    }

    public function state(Game $game): JsonResponse
    {
        $this->authorize('view', $game);

        return response()->json($this->stateData($game));
    }

    public function cancel(Game $game): RedirectResponse
    {
        $this->authorize('update', $game);
        if (in_array($game->status, ['completed', 'cancelled'], true)) {
            return back()->withErrors(['game' => 'This game can no longer be cancelled.']);
        }
        DB::transaction(function () use ($game): void {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (in_array($lockedGame->status, ['draft', 'in_progress'], true)) {
                $lockedGame->update(['status' => 'cancelled']);
            }
        });

        return redirect()->route('history.show', $game)->with('success', 'Game cancelled.');
    }

    /** @return array<string, mixed> */
    private function stateData(Game $game): array
    {
        $game->loadMissing(['rounds.spins']);
        $round = $game->rounds->last();
        if (! $round) {
            return ['game' => ['name' => $game->name, 'status' => $game->status], 'round' => null];
        }
        $winnerIds = $round->spins->pluck('participant_id')->map(fn ($id): int => (int) $id)->all();
        $lastSpin = $round->spins->last();
        $pool = app(RoundService::class)->poolForRound($game, $round->round_number);
        $poolCount = $pool->count();
        $poolById = $pool->keyBy('participant_id');
        $names = $lastSpin?->meta['segments'] ?? $pool->reject(fn ($entry): bool => in_array((int) $entry->participant_id, $winnerIds, true))->shuffle()->take(config('game.wheel_visible_segments'))->map(fn ($entry): array => ['name' => $entry->name, 'username' => $entry->username])->all();
        $selectedPlayers = $round->spins->map(function ($spin) use ($poolById): array {
            $participant = $poolById->get($spin->participant_id);

            return [
                'name' => $spin->meta['winner_name'],
                'username' => $spin->meta['winner_username'],
                'spin' => $spin->spin_number,
                'priority' => ($participant?->priority ?? 0) > 0,
                'wasPriority' => $spin->was_priority,
            ];
        })->values();

        return [
            'game' => ['name' => $game->name, 'status' => $game->status, 'participantCount' => $game->participant_count, 'roundsConfig' => $game->rounds_config, 'spinDurationMs' => config('game.spin_duration_ms')],
            'round' => ['number' => $round->round_number, 'winnersCount' => $round->winners_count, 'status' => $round->status, 'spinCount' => $round->spins->count(), 'poolCount' => $poolCount, 'remainingPoolCount' => max(0, $poolCount - $round->spins->count()), 'selectedPlayers' => $selectedPlayers, 'winners' => $selectedPlayers, 'segments' => array_values($names)],
        ];
    }
}
