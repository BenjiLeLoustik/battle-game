<?php

namespace App\Shared\Engage;

use App\Entity\Game;
use App\Entity\GameEvent;
use App\Repository\GameEventRepository;
use DateTime;

class EventManager
{
    protected ?array $live = null;

    public function __construct(protected GameEventRepository $events)
    {
    }

    public function live(): array
    {
        return $this->live ??= array_values(array_filter($this->events->findBy(['active' => true], ['startsAt' => 'ASC']), fn (GameEvent $event): bool => $event->isLive()));
    }

    public function upcoming(int $limit = 3): array
    {
        $now = new DateTime();

        return array_slice(array_values(array_filter($this->events->findBy(['active' => true], ['startsAt' => 'ASC']), fn (GameEvent $event): bool => $event->getStartsAt() > $now)), 0, $limit);
    }

    public function multipliers(?Game $game): array
    {
        $xp = 1.0;
        $coins = 1.0;

        foreach ($this->live() as $event) {
            if ($event->appliesTo($game)) {
                $xp = max($xp, $event->getXpMultiplier());
                $coins = max($coins, $event->getCoinsMultiplier());
            }
        }

        return ['xp' => $xp, 'coins' => $coins];
    }

    public function forGame(Game $game): ?GameEvent
    {
        foreach ($this->live() as $event) {
            if ($event->appliesTo($game) && ($event->getXpMultiplier() > 1 || $event->getCoinsMultiplier() > 1 || $event->getGame() !== null)) {
                return $event;
            }
        }

        return null;
    }
}
