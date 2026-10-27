<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Participant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $activeGame = Game::query()->where('status', 'in_progress')->latest('started_at')->first();

        return Inertia::render('Dashboard', [
            'stats' => ['participants' => Participant::where('is_active', true)->count(), 'priority' => Participant::where('is_active', true)->where('priority', '>', 0)->count(), 'completedGames' => Game::where('status', 'completed')->count()],
            'activeGame' => $activeGame,
            'recentGames' => Game::query()->with('starter')->latest()->limit(5)->get(),
            'user' => $request->user()->only(['name', 'email']),
        ]);
    }
}
