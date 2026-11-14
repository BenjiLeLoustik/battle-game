<?php

namespace App\Controller\Site;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Shared\Game\GameMatchManager;
use App\Shared\Game\GameStatsManager;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Shop\SubscriptionManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/games', name: 'game_')]
class GameController extends AbstractController
{
    private const ALTERNATIVES = 3;

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        string $slug,
        GameRepository $games,
        GameMatchManager $matches,
        GameStatsManager $stats,
        MaintenanceManager $maintenance
    ): Response {
        $game = $games->findPlayable($slug, $this->isGranted('ROLE_ADMIN'))
            ?? throw $this->createNotFoundException();
        $online = $matches->online();
        $down = $maintenance->isGameDown($game);

        return $this->render('@site/pages/game/show.html.twig', [
            'game' => $game,
            'online' => $online[$game->getId()] ?? 0,
            'stats' => $stats->page($game, $this->getUser()),
            'maintenance' => $maintenance->warning($game),
            'down' => $down,
            'blocked' => $maintenance->blocksNewMatches($game),
            'locked' => !$this->get(SubscriptionManager::class)->canAccess($this->getUser(), $game),
            'alternatives' => $down ? $this->alternatives($game, $games, $maintenance, $online) : [],
        ]);
    }

    protected function alternatives(Game $game, GameRepository $games, MaintenanceManager $maintenance, array $online): array
    {
        $available = array_filter($games->findLive(), fn (Game $other): bool => $other->getId() !== $game->getId() && !$maintenance->isGameDown($other));
        usort($available, fn (Game $a, Game $b): int => ($online[$b->getId()] ?? 0) <=> ($online[$a->getId()] ?? 0));

        return array_map(fn (Game $other): array => ['game' => $other, 'online' => $online[$other->getId()] ?? 0], array_slice($available, 0, self::ALTERNATIVES));
    }
}
