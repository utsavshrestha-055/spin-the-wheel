<?php

$rounds = array_map('trim', explode(',', (string) env('GAME_ROUNDS', '15,3,1')));
$rounds = array_map(static fn (string $round): int => ctype_digit($round) ? (int) $round : 0, $rounds);
$previous = null;

foreach ($rounds as $count) {
    if ($count < 1) {
        throw new InvalidArgumentException('GAME_ROUNDS must be a comma-separated list of positive integers.');
    }

    if ($previous !== null && $count > $previous) {
        throw new InvalidArgumentException('Each GAME_ROUNDS entry must be no larger than the previous round.');
    }

    $previous = $count;
}

$duration = (int) env('GAME_SPIN_DURATION_MS', 6000);
$segments = (int) env('GAME_WHEEL_VISIBLE_SEGMENTS', 24);

if ($duration < 500 || $segments < 4 || $segments > 100) {
    throw new InvalidArgumentException('GAME_SPIN_DURATION_MS must be at least 500 and GAME_WHEEL_VISIBLE_SEGMENTS must be between 4 and 100.');
}

return [
    'rounds' => $rounds,
    'spin_duration_ms' => $duration,
    'priority_carry_forward' => (bool) env('GAME_PRIORITY_CARRY_FORWARD', true),
    'wheel_visible_segments' => $segments,
];
