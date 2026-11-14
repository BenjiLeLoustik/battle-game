<?php

namespace App\Repository;

use App\Admin\Studio\Enum\GameTaskStatusEnum;
use App\Entity\Game;
use App\Entity\GameTask;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameTaskRepository extends AbstractRepository
{
    protected string $entityClass = GameTask::class;

    public function countOpen(): array
    {
        $rows = $this->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT game_id, COUNT(*) AS total FROM game_task WHERE status <> :done GROUP BY game_id',
            ['done' => GameTaskStatusEnum::DONE->value],
        );

        return array_column(array_map(fn (array $row): array => ['game' => (int) $row['game_id'], 'total' => (int) $row['total']], $rows), 'total', 'game');
    }

    public function findByGame(Game $game): array
    {
        $order = array_flip(array_map(fn (GameTaskStatusEnum $status): string => $status->value, [GameTaskStatusEnum::DOING, GameTaskStatusEnum::TODO, GameTaskStatusEnum::DONE]));
        $tasks = $this->findBy(['game' => $game], ['id' => 'DESC']);

        usort($tasks, fn (GameTask $a, GameTask $b): int => [$order[$a->getStatus()->value], $a->getEndAt()?->format('Y-m-d') ?? '9999'] <=> [$order[$b->getStatus()->value], $b->getEndAt()?->format('Y-m-d') ?? '9999']);

        return $tasks;
    }
}
