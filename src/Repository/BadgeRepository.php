<?php

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\Game;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class BadgeRepository extends AbstractRepository
{
    protected string $entityClass = Badge::class;

    public function findGeneral(): array
    {
        return array_values(array_filter(
            $this->findBy(['active' => true], ['position' => 'ASC', 'id' => 'ASC']),
            fn (Badge $badge): bool => $badge->getGame() === null,
        ));
    }

    public function findForGame(Game $game): array
    {
        return $this->findBy(['game' => $game, 'active' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
