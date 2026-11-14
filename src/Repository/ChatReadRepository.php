<?php

namespace App\Repository;

use App\Entity\ChatRead;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ChatReadRepository extends AbstractRepository
{
    protected string $entityClass = ChatRead::class;
}
