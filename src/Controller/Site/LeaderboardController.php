<?php

namespace App\Controller\Site;

use App\Repository\GameRepository;
use App\Shared\Leaderboard\Enum\LeaderboardPeriodEnum;
use App\Shared\Leaderboard\LeaderboardManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/leaderboard', name: 'leaderboard_')]
class LeaderboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, GameRepository $games, LeaderboardManager $leaderboard): Response
    {
        $slug = (string) $request->query->get('game');
        $game = $slug !== '' ? $games->findPlayable($slug, false) : null;

        return $this->render('@site/pages/leaderboard/index.html.twig', [
            'games' => $games->findLive(),
            'game' => $game,
            'board' => $leaderboard->build($game, LeaderboardPeriodEnum::tryFrom((string) $request->query->get('period')), $this->getUser()),
        ]);
    }
}