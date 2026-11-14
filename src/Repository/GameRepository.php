<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Game;
use App\Shared\Game\Enum\GameStatusEnum;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameRepository extends AbstractRepository
{
    protected string $entityClass = Game::class;

    public function findLive(): array
    {
        return $this->findBy(['status' => GameStatusEnum::LIVE], ['name' => 'ASC']);
    }

    public function findLivePage(?Category $category, int $page, int $size): array
    {
        $criteria = ['status' => GameStatusEnum::LIVE] + ($category === null ? [] : ['category' => $category]);
        $games = $this->findBy($criteria, ['name' => 'ASC'], $size + 1, (max(1, $page) - 1) * $size);

        return [array_slice($games, 0, $size), count($games) > $size];
    }

    public function findFeatured(bool $live = false): array
    {
        $games = $this->findBy([], ['featuredPosition' => 'ASC']);

        return array_values(array_filter($games, fn (Game $game): bool => $game->isFeatured() && (!$live || $game->getStatus() === GameStatusEnum::LIVE)));
    }

    public function sortFeatured(array $games): void
    {
        foreach (array_values($games) as $index => $game) {
            $this->save($game->setFeaturedPosition($index + 1));
        }

        $this->getOrm()->flush();
    }

    public function findPlayable(string $slug, bool $tester): ?Game
    {
        $game = $this->findOneBy(['slug' => $slug]);

        return $game !== null && $game->isPlayableBy($tester) ? $game : null;
    }
}