<?php

namespace Tests\Feature;

use App\Models\GameParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\GameService;
use App\Services\RoundService;
use App\Services\SpinPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_players_are_guaranteed_and_scattered_in_the_first_round(): void
    {
        $pool = collect(range(1, 400))->map(fn (int $id): GameParticipant => new GameParticipant(['participant_id' => $id, 'priority' => $id <= 3 ? 1 : 0]));
        $planner = app(SpinPlanner::class);
        $positions = [];

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $plan = $planner->plan($pool, 15, true);
            $ids = array_column($plan, 'participant_id');
            $this->assertCount(15, $ids);
            $this->assertCount(15, array_unique($ids));
            $this->assertContains(1, $ids);
            $this->assertContains(2, $ids);
            $this->assertContains(3, $ids);
            $positions[] = array_search(1, $ids, true);
        }

        $this->assertGreaterThan(1, count(array_unique($positions)));
    }

    public function test_priority_overflow_chooses_highest_priority_with_random_tie_breaking(): void
    {
        $pool = collect([
            new GameParticipant(['participant_id' => 1, 'priority' => 2]),
            new GameParticipant(['participant_id' => 2, 'priority' => 10]),
            new GameParticipant(['participant_id' => 3, 'priority' => 10]),
            new GameParticipant(['participant_id' => 4, 'priority' => 0]),
        ]);
        $plan = app(SpinPlanner::class)->plan($pool, 2, true);
        $ids = array_column($plan, 'participant_id');

        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
        $this->assertNotContains(1, $ids);
        $this->assertNotContains(4, $ids);
    }

    public function test_game_configuration_rejects_a_round_larger_than_its_predecessor(): void
    {
        $previousEnvironment = [$_ENV['GAME_ROUNDS'] ?? null, $_SERVER['GAME_ROUNDS'] ?? null];
        $_ENV['GAME_ROUNDS'] = '3,4';
        $_SERVER['GAME_ROUNDS'] = '3,4';

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Each GAME_ROUNDS entry must be no larger than the previous round.');
            require base_path('config/game.php');
        } finally {
            if ($previousEnvironment[0] === null) {
                unset($_ENV['GAME_ROUNDS']);
            } else {
                $_ENV['GAME_ROUNDS'] = $previousEnvironment[0];
            }
            if ($previousEnvironment[1] === null) {
                unset($_SERVER['GAME_ROUNDS']);
            } else {
                $_SERVER['GAME_ROUNDS'] = $previousEnvironment[1];
            }
        }
    }

    public function test_game_progresses_using_only_previous_round_winners_and_completes(): void
    {
        config(['game.rounds' => [2, 1], 'game.priority_carry_forward' => true, 'game.wheel_visible_segments' => 4]);
        $admin = User::factory()->create();
        Participant::factory()->count(5)->create();
        $priority = Participant::factory()->create(['priority' => 1]);
        $game = app(GameService::class)->create($admin, 'Test draw');
        $this->assertSame('in_progress', $game->status);
        $this->assertSame(6, $game->participants()->count());

        $firstSpin = app(GameService::class)->spin($game);
        try {
            app(GameService::class)->spin($game);
            $this->fail('A second spin must wait until the first animation is complete.');
        } catch (\DomainException $exception) {
            $this->assertSame('Let the current spin finish before starting another.', $exception->getMessage());
        }
        $this->travel(7)->seconds();
        $secondSpin = app(GameService::class)->spin($game);
        $this->assertNotSame($firstSpin->participant_id, $secondSpin->participant_id);
        $this->assertSame(2, $game->rounds()->firstOrFail()->spins()->count());
        $this->assertDatabaseHas('game_spins', ['game_round_id' => $game->rounds()->firstOrFail()->id, 'was_priority' => true, 'participant_id' => $priority->id]);

        app(RoundService::class)->start($game, 2);
        $thirdSpin = app(GameService::class)->spin($game->fresh());
        $this->assertSame($priority->id, $thirdSpin->participant_id);
        $this->assertSame('completed', $game->fresh()->status);
        $this->assertSame(1, $game->rounds()->where('round_number', 2)->firstOrFail()->spins()->count());
    }

    public function test_spin_post_redirects_with_the_server_result_for_the_inertia_page(): void
    {
        config(['game.rounds' => [1], 'game.priority_carry_forward' => true, 'game.wheel_visible_segments' => 4]);
        $admin = User::factory()->create();
        Participant::factory()->create(['username' => 'only_player']);
        $game = app(GameService::class)->create($admin, 'One winner');

        $this->actingAs($admin)
            ->from(route('games.show', $game))
            ->post(route('games.spin', $game))
            ->assertRedirect(route('games.show', $game))
            ->assertSessionHas('spin.spin.number', 1)
            ->assertSessionHas('spin.spin.winner.username', 'only_player')
            ->assertSessionHas('spin.state.round.segments.0.username', 'only_player');
    }

    public function test_next_round_pool_contains_only_previous_round_winners_and_spins_never_select_eliminated_players(): void
    {
        config(['game.rounds' => [2, 2], 'game.priority_carry_forward' => true, 'game.wheel_visible_segments' => 4]);
        $admin = User::factory()->create();
        $participants = Participant::factory()->count(6)->create();
        $game = app(GameService::class)->create($admin, 'Pool audit');

        $roundOne = $game->rounds()->where('round_number', 1)->firstOrFail();
        $roundOneWinners = [app(GameService::class)->spin($game)->participant_id];
        $this->travel(7)->seconds();
        $roundOneWinners[] = app(GameService::class)->spin($game->fresh())->participant_id;
        $eliminatedIds = $participants->pluck('id')->diff($roundOneWinners)->values()->all();

        app(RoundService::class)->start($game->fresh(), 2);
        $roundTwo = $game->rounds()->where('round_number', 2)->firstOrFail();
        $roundTwoPlanIds = array_column($roundTwo->spin_plan, 'participant_id');
        $roundTwoPoolIds = app(RoundService::class)->poolForRound($game->fresh(), 2)->pluck('participant_id')->all();

        $this->assertEqualsCanonicalizing($roundOneWinners, $roundTwoPlanIds);
        $this->assertEqualsCanonicalizing($roundOneWinners, $roundTwoPoolIds);
        $this->assertSame(2, $roundTwo->winners_count);
        $this->assertCount($roundTwo->winners_count, $roundTwoPoolIds);

        $roundTwoWinners = [app(GameService::class)->spin($game->fresh())->participant_id];
        $this->travel(7)->seconds();
        $roundTwoWinners[] = app(GameService::class)->spin($game->fresh())->participant_id;
        $this->assertEqualsCanonicalizing($roundOneWinners, $roundTwoWinners);
        foreach ($roundTwoWinners as $winnerId) {
            $this->assertNotContains($winnerId, $eliminatedIds);
        }
    }

    public function test_priority_is_not_carried_into_later_rounds_when_disabled(): void
    {
        config(['game.rounds' => [2, 1], 'game.priority_carry_forward' => false, 'game.wheel_visible_segments' => 4]);
        $admin = User::factory()->create();
        Participant::factory()->count(2)->create();
        Participant::factory()->create(['priority' => 5]);
        $game = app(GameService::class)->create($admin, 'Priority setting');

        app(GameService::class)->spin($game);
        $this->travel(7)->seconds();
        app(GameService::class)->spin($game->fresh());
        app(RoundService::class)->start($game->fresh(), 2);

        $roundTwo = $game->rounds()->where('round_number', 2)->firstOrFail();
        $this->assertSame([false], array_column($roundTwo->spin_plan, 'was_priority'));
    }

    public function test_spin_endpoint_rejects_a_planned_player_outside_the_current_round_pool(): void
    {
        config(['game.rounds' => [1, 1], 'game.priority_carry_forward' => true, 'game.wheel_visible_segments' => 4]);
        $admin = User::factory()->create();
        $participants = Participant::factory()->count(3)->create();
        $game = app(GameService::class)->create($admin, 'Invalid pool plan');
        $roundOneWinner = app(GameService::class)->spin($game)->participant_id;
        $eliminatedId = $participants->pluck('id')->first(fn (int $id): bool => $id !== $roundOneWinner);

        app(RoundService::class)->start($game->fresh(), 2);
        $roundTwo = $game->rounds()->where('round_number', 2)->firstOrFail();
        $roundTwo->update(['spin_plan' => [['participant_id' => $eliminatedId, 'was_priority' => false]]]);

        $this->actingAs($admin)
            ->from(route('games.show', $game))
            ->post(route('games.spin', $game))
            ->assertRedirect(route('games.show', $game))
            ->assertSessionHasErrors(['spin' => 'The planned player is not eligible for this round.']);
    }
}
