<?php

namespace App\Admin\Security;

use App\Entity\User;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Exception\AccessDeniedHttpException;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener(priority: -5)]
class AdminAccessListener
{
    public function __construct(
        protected AdminAccess $access,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $permission = $this->access->permissionFor($event->getRequest()->getPath());
        $user = $this->security->getUser();

        if ($permission !== null && $user instanceof User && $user->isAdmin() && !$this->access->can($user, $permission)) {
            throw new AccessDeniedHttpException();
        }
    }
}
