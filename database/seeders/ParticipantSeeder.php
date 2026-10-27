<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        Participant::factory()->count(397)->create();
        Participant::query()->updateOrCreate(['username' => 'priority_1'], ['name' => 'Priority Player 1', 'priority' => 1, 'is_active' => true]);
        Participant::query()->updateOrCreate(['username' => 'priority_2'], ['name' => 'Priority Player 2', 'priority' => 2, 'is_active' => true]);
        Participant::query()->updateOrCreate(['username' => 'priority_3'], ['name' => 'Priority Player 3', 'priority' => 3, 'is_active' => true]);
    }
}
