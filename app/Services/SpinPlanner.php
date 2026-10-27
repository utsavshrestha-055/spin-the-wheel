<?php

namespace App\Services;

use App\Models\GameParticipant;
use Illuminate\Support\Collection;

class SpinPlanner
{
    /** @param Collection<int, GameParticipant> $pool
     * @return array<int, array{participant_id: int, was_priority: bool}>
     */
    public function plan(Collection $pool, int $winnerCount, bool $carryPriority): array
    {
        if ($winnerCount > $pool->count()) {
            throw new \InvalidArgumentException('The round has more winners than eligible participants.');
        }

        $priority = $pool->filter(fn (GameParticipant $entry): bool => $carryPriority && $entry->priority > 0)->values()->all();
        $priority = $this->shuffle($priority);
        usort($priority, static fn (GameParticipant $left, GameParticipant $right): int => $right->priority <=> $left->priority);
        $guaranteed = array_slice($priority, 0, min($winnerCount, count($priority)));
        $guaranteedIds = array_fill_keys(array_map(static fn (GameParticipant $entry): int => $entry->participant_id, $guaranteed), true);
        $others = $this->shuffle($pool->reject(static fn (GameParticipant $entry): bool => isset($guaranteedIds[$entry->participant_id]))->values()->all());
        $selected = [...$guaranteed, ...array_slice($others, 0, $winnerCount - count($guaranteed))];

        return array_map(static fn (GameParticipant $entry): array => [
            'participant_id' => $entry->participant_id,
            'was_priority' => $carryPriority && $entry->priority > 0,
        ], $this->shuffle($selected));
    }

    /** @template T @param array<int, T> $items @return array<int, T> */
    private function shuffle(array $items): array
    {
        for ($index = count($items) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
        }

        return $items;
    }
}
