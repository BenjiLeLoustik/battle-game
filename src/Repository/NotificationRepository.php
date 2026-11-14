<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class NotificationRepository extends AbstractRepository
{
    protected string $entityClass = Notification::class;

    public function countUnread(User $user): int
    {
        return (int) $this->getOrm()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM notification WHERE user_id = :user AND read_at IS NULL',
            ['user' => $user->getId()],
        );
    }
}
