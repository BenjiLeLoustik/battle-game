<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class ScoreThresholdRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'score_threshold';
    }

    public function getParameters(): array
    {
        return ['stat' => 'string', 'value' => 'float', 'compare' => 'string', 'aggregate' => 'string'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        $target = (float) ($params['value'] ?? 0);
        $best = $game === null ? null : $stats->best($user, $game, (string) ($params['stat'] ?? ''), $params['aggregate'] ?? null);

        if ($best === null) {
            return [0, 1];
        }

        if (($params['compare'] ?? '>=') === '<=') {
            return $best <= $target ? [1, 1] : [min(0.99, $target / max($best, 0.0001)), 1];
        }

        return [min($best, $target), max($target, 0.0001)];
    }
}
