<?php

namespace App\Site\Security\Listener;

use App\Site\Security\DiscordRegistrationManager;
use App\Site\Security\Exception\DiscordRegistrationRequiredException;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\RedirectResponse;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\Security\Event\LoginFailureEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener]
class DiscordRegistrationListener
{
    public function __construct(
        protected RoutingManagerInterface $routing,
        protected SessionManagerInterface $session,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $this->session->remove(DiscordRegistrationManager::LINK_KEY);

        if (!$event->getException() instanceof DiscordRegistrationRequiredException) {
            return;
        }

        $this->session->remove(SecurityManagerInterface::class);
        $event->setResponse(new RedirectResponse($this->routing->generate('site_security_discord_register')));
    }
}