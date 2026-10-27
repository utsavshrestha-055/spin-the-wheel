<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ParticipantController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::get('/games/{game}/stage', [GameController::class, 'stage'])->name('games.stage');
Route::get('/games/{game}/state', [GameController::class, 'state'])->name('games.state');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class.'@__invoke')->name('dashboard');
    Route::get('/participants', [ParticipantController::class, 'index'])->name('participants.index');
    Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::post('/participants/import', [ParticipantController::class, 'import'])->middleware('throttle:10,1')->name('participants.import');
    Route::put('/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
    Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('/games/{game}/spin', [GameController::class, 'spin'])->middleware('throttle:30,1')->name('games.spin');
    Route::post('/games/{game}/rounds', [GameController::class, 'nextRound'])->name('games.rounds.store');
    Route::post('/games/{game}/cancel', [GameController::class, 'cancel'])->name('games.cancel');
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/{game}', [HistoryController::class, 'show'])->name('history.show');
    Route::get('/history/{game}/export', [HistoryController::class, 'export'])->name('history.export');
});
