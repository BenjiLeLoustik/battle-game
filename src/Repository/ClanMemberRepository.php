<?php

namespace App\Repository;

use App\Entity\ClanMember;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ClanMemberRepository extends AbstractRepository
{
    protected string $entityClass = ClanMember::class;
}
