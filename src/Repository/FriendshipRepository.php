<?php

namespace App\Repository;

use App\Entity\Friendship;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class FriendshipRepository extends AbstractRepository
{
    protected string $entityClass = Friendship::class;
}
