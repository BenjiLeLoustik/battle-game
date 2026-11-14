<?php

namespace App\Site\Security\Listener;

use App\Entity\User;
use App\Site\Account\AccountManager;
use App\Site\Security\DiscordRegistrationManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\RedirectResponse;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Security\Event\LoginSuccessEvent;

#[AsListener]
class LoginSuccessListener
{
    public function __construct(
        protected AccountManager $accounts,
        protected DiscordRegistrationManager $discord,
        protected RoutingManagerInterface $routing,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof User && $user->getDeletionRequestedAt() !== null) {
            $this->accounts->cancelDeletion($user);
        }

        if ($this->discord->finishLinking()) {
            $event->setResponse(new RedirectResponse($this->routing->generate('site_settings_account')));
        }
    }
}
