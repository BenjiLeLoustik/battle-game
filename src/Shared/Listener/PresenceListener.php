<?php

namespace App\Shared\Listener;

use App\Entity\User;
use App\Shared\Social\PresenceManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener(priority: -10)]
class PresenceListener
{
    public function __construct(protected PresenceManager $presence, protected SecurityManagerInterface $security)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $this->presence->touch($user);
        }
    }
}
