<?php

namespace App\Repository;

use App\Entity\UserItem;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class UserItemRepository extends AbstractRepository
{
    protected string $entityClass = UserItem::class;
}
