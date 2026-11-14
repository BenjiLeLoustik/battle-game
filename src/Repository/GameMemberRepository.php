<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameMember;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameMemberRepository extends AbstractRepository
{
    protected string $entityClass = GameMember::class;

    public function teams(): array
    {
        $teams = [];

        foreach ($this->findBy([], ['id' => 'ASC']) as $member) {
            $teams[$member->getGame()->getId()][] = $member;
        }

        return $teams;
    }
}
