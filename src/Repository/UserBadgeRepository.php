<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBadge;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class UserBadgeRepository extends AbstractRepository
{
    protected string $entityClass = UserBadge::class;

    public function obtained(User $user): array
    {
        $obtained = [];

        foreach ($this->findBy(['user' => $user], ['id' => 'DESC']) as $userBadge) {
            $obtained[$userBadge->getBadge()->getId()] = $userBadge;
        }

        return $obtained;
    }
}
