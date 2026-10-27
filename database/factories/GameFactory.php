<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'Lucky draw', 'status' => 'draft', 'participant_count' => 0, 'rounds_config' => config('game.rounds'), 'priority_carry_forward' => true];
    }
}
