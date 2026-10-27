<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Game::class);
        $games = Game::query()->with('starter')->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest()->paginate(12)->withQueryString();

        return Inertia::render('History/Index', ['games' => $games, 'filters' => $request->only('status')]);
    }

    public function show(Game $game): Response
    {
        $this->authorize('view', $game);
        $game->load(['starter', 'rounds.spins']);
        $rounds = $game->rounds->map(fn ($round): array => [
            'round_number' => $round->round_number,
            'status' => $round->status,
            'spins' => $round->spins->map(fn ($spin): array => [
                'id' => $spin->id,
                'spin_number' => $spin->spin_number,
                'winner_name' => $spin->meta['winner_name'],
                'winner_username' => $spin->meta['winner_username'],
                'was_priority' => $spin->was_priority,
                'spun_at' => $spin->spun_at,
            ])->all(),
        ])->all();

        return Inertia::render('History/Show', ['game' => $game->only(['public_id', 'name', 'status', 'participant_count', 'started_at', 'created_at', 'rounds_config']), 'starter' => $game->starter?->only(['name']), 'rounds' => $rounds]);
    }

    public function export(Game $game)
    {
        $this->authorize('view', $game);
        $game->load('rounds.spins');
        $filename = 'game-'.$game->public_id.'-results.csv';

        return response()->streamDownload(function () use ($game): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['round', 'spin', 'name', 'username', 'priority', 'selected_at']);
            foreach ($game->rounds as $round) {
                foreach ($round->spins as $spin) {
                    fputcsv($output, [$round->round_number, $spin->spin_number, $spin->meta['winner_name'], $spin->meta['winner_username'], $spin->was_priority ? 'yes' : 'no', $spin->spun_at->toIso8601String()]);
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
