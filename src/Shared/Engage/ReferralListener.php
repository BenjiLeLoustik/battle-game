<?php

namespace App\Shared\Engage;

use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener(priority: -10)]
class ReferralListener
{
    public function __construct(
        protected ReferralManager $referrals,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $ref = $request->query->get('ref');

        if ($request->getMethod() === 'GET' && is_string($ref) && preg_match('/^[A-Za-z0-9_]{3,30}$/', $ref) && $this->security->getUser() === null) {
            $this->referrals->capture($ref);
        }
    }
}
