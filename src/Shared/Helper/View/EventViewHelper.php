<?php

namespace App\Shared\Helper\View;

use App\Entity\Game;
use App\Shared\Engage\EventManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class EventViewHelper implements ViewFunctionInterface
{
    public function __construct(protected EventManager $events)
    {
    }

    public function getName(): string
    {
        return 'game_events';
    }

    public function __invoke(?Game $game = null): array
    {
        if ($game === null) {
            return $this->events->live();
        }

        $event = $this->events->forGame($game);

        return $event === null ? [] : [$event];
    }
}
