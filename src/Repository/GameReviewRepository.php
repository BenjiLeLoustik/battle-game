<?php

namespace App\Repository;

use App\Admin\Studio\Enum\GameReviewStatusEnum;
use App\Entity\Game;
use App\Entity\GameReview;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameReviewRepository extends AbstractRepository
{
    protected string $entityClass = GameReview::class;

    public function findPending(Game $game): ?GameReview
    {
        return $this->findOneBy(['game' => $game, 'status' => GameReviewStatusEnum::PENDING]);
    }

    public function findLatest(Game $game, int $limit = 5): array
    {
        return $this->findBy(['game' => $game], ['id' => 'DESC'], $limit);
    }
}
