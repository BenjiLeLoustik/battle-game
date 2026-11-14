<?php

namespace App\Shared\Helper\View;

use App\Entity\User;
use App\Repository\NotificationRepository;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

class NotificationViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected NotificationRepository $notifications,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function getName(): string
    {
        return 'notifications_unread';
    }

    public function __invoke(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User && !$user->isGuest() ? $this->notifications->countUnread($user) : 0;
    }
}
