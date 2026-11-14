<?php

namespace App\Sdk;

use App\Sdk\Contract\GameServerInterface;
use App\Sdk\Trait\ClockTrait;

abstract class AbstractSdkServer implements GameServerInterface
{
    use ClockTrait;

    public function view(array $state): array
    {
        return $state;
    }

    public function secret(array $state, string $player): array
    {
        return [];
    }

    public function hasSecret(): bool
    {
        return (new \ReflectionMethod($this, 'secret'))->getDeclaringClass()->getName() !== self::class;
    }

    public function scoreboard(array $state): array
    {
        $scores = $state['scores'] ?? [];
        arsort($scores);

        return array_map(fn (int|string $player): array => [
            'player' => (string) $player,
            'score' => (string) $scores[$player],
            'detail' => '',
        ], array_keys($scores));
    }

    public function progress(array $state): ?array
    {
        return isset($state['round'], $state['rounds'])
            ? ['current' => $state['round'], 'total' => $state['rounds']]
            : null;
    }

    public function isFinished(array $state): bool
    {
        return ($state['finished'] ?? false) === true;
    }

    public function results(array $state): array
    {
        $results = [];

        foreach ($this->scoreboard($state) as $index => $row) {
            $results[$row['player']] = [
                'position' => $index + 1,
                'stats' => []
            ];
        }

        return $results;
    }
}