<?php

namespace App\Controller\Site;

use App\Entity\Game;
use App\Entity\GameMatch;
use App\Repository\GameMatchRepository;
use App\Repository\GameRepository;
use App\Sdk\Trait\ClockTrait;
use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameManager;
use App\Shared\Game\Enum\GameMatchModeEnum;
use App\Shared\Game\GameMatchManager;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\SubscriptionManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;
use NeoPHP\Package\WebProfiler\WebProfilerManagerInterface;

#[Route('/play', name: 'play_')]
#[IsGranted('IS_AUTHENTICATED')]
class PlayController extends AbstractController
{
    use ClockTrait;

    public const EMOTES = ['thumbs', 'laugh', 'wow', 'angry', 'fire', 'gg'];

    public function __construct(
        protected GameMatchRepository $repository,
        protected GameMatchManager $matches,
        protected MaintenanceManager $maintenance,
        protected LifeManager $lives,
        protected SubscriptionManager $subscriptions,
    ) {
    }

    #[Route('/clock/now', name: 'clock', methods: ['GET'])]
    public function clock(): Response
    {
        return $this->json(['time' => $this->now()]);
    }

    #[Route('/{slug}', name: 'quick', methods: ['GET'])]
    public function quick(string $slug, GameRepository $games): Response
    {
        $game = $games->findPlayable($slug, $this->isGranted('ROLE_ADMIN')) ?? throw $this->createNotFoundException();

        if ($this->maintenance->blocksNewMatches($game)) {
            return $this->redirectToRoute('site_game_show', ['slug' => $game->getSlug()]);
        }

        if (!$this->subscriptions->canAccess($this->getUser(), $game)) {
            $this->addFlash('error', 'error.shop.early_access');

            return $this->redirectToRoute('site_shop_index', ['tab' => 'subscription']);
        }

        if (!$this->lives->canPlay($this->getUser())) {
            $this->addFlash('error', 'error.shop.lives');

            return $this->redirectToRoute('site_shop_index', ['tab' => 'lives']);
        }

        return $this->redirectToRoute('site_play_show', [
            'code' => $this->matches->quickPlay($game, $this->getUser())->getCode()
        ]);
    }

    #[Route('/{slug}/ranked', name: 'ranked', methods: ['GET'])]
    public function ranked(string $slug, GameRepository $games): Response
    {
        $game = $games->findPlayable($slug, $this->isGranted('ROLE_ADMIN')) ?? throw $this->createNotFoundException();
        $user = $this->getUser();

        if ($user->isGuest() || $game->getMaxPlayers() < 2) {
            $this->addFlash('error', 'site.ranked.account_only');

            return $this->redirectToRoute('site_game_show', ['slug' => $game->getSlug()]);
        }

        if ($this->maintenance->blocksNewMatches($game)) {
            return $this->redirectToRoute('site_game_show', ['slug' => $game->getSlug()]);
        }

        if (!$this->subscriptions->canAccess($user, $game)) {
            $this->addFlash('error', 'error.shop.early_access');

            return $this->redirectToRoute('site_shop_index', ['tab' => 'subscription']);
        }

        if (!$this->lives->canPlay($user)) {
            $this->addFlash('error', 'error.shop.lives');

            return $this->redirectToRoute('site_shop_index', ['tab' => 'lives']);
        }

        return $this->redirectToRoute('site_play_show', ['code' => $this->matches->quickPlay($game, $user, GameMatchModeEnum::RANKED)->getCode()]);
    }

    #[Route('/match/{code}/emote', name: 'emote', methods: ['POST'])]
    #[Csrf('game-match-{code}')]
    public function emote(string $code, Request $request, MercureManagerInterface $mercure, SessionManagerInterface $session): Response
    {
        $match = $this->find($code);
        $emote = (string) ($request->toArray()['emote'] ?? '');
        $last = (float) $session->get('_battlegame.emote', 0);

        if (!in_array($emote, self::EMOTES, true) || !$match->hasPlayer($this->getUser()) || microtime(true) - $last < 1.5) {
            return $this->json(['ok' => false], 429);
        }

        $session->set('_battlegame.emote', microtime(true));
        $mercure->publish(Update::json($this->matches->topic($match), ['type' => 'emote', 'player' => (string) $this->getUser()->getId(), 'emote' => $emote]));

        return $this->json(['ok' => true]);
    }

    #[Route('/{slug}/solo', name: 'solo', methods: ['GET'])]
    public function solo(string $slug, Request $request, GameRepository $games, GameManager $manager): Response
    {
        $game = ($this->isPreview($request) ? $games->findOneBy(['slug' => $slug]) : $games->findPlayable($slug, $this->isGranted('ROLE_ADMIN'))) ?? throw $this->createNotFoundException();

        if ($this->isPreview($request)) {
            $game = $this->sync($game, $manager);
        }

        if (!$game->isSolo()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isPreview($request) && !$this->subscriptions->canAccess($this->getUser(), $game)) {
            $this->addFlash('error', 'error.shop.early_access');

            return $this->redirectToRoute('site_shop_index', ['tab' => 'subscription']);
        }

        if (!$this->isPreview($request) && $this->maintenance->blocksNewMatches($game)) {
            return $this->redirectToRoute('site_game_show', ['slug' => $game->getSlug()]);
        }

        return $this->redirectToRoute('site_play_show', [
            'code' => $this->matches->solo($game, $this->getUser(), $this->isPreview($request))->getCode(),
            'preview' => $this->isPreview($request) ? 1 : null,
        ]);
    }

    #[Route('/match/{code}', name: 'show', methods: ['GET'])]
    public function show(string $code, Request $request, GameManager $games, ?WebProfilerManagerInterface $profiler): Response
    {
        $match = $this->find($code);
        $preview = $this->isPreview($request);
        $this->matches->interrupt($match);
        $this->matches->join($match, $this->getUser());

        if ($preview) {
            $profiler?->disable();
        }

        return $this->render('@game/match.html.twig', [
            'match' => $match,
            'game' => $match->getGame(),
            'snapshot' => $this->matches->snapshot($match),
            'secret' => $this->matches->secret($match, $this->getUser()),
            'topic' => $this->matches->topic($match),
            'languages' => $games->languages($match->getGame()),
            'preview' => $preview,
        ]);
    }

    #[Route('/match/{code}/action', name: 'action', methods: ['POST'])]
    #[Csrf('game-match-{code}')]
    public function action(string $code, Request $request): Response
    {
        $data = $request->toArray();
        $this->matches->play(
            $this->find($code),
            $this->getUser(),
            (string) ($data['action'] ?? ''),
            (array) ($data['payload'] ?? [])
        );

        return $this->json(['ok' => true]);
    }

    #[Route('/match/{code}/secret', name: 'secret', methods: ['GET'])]
    public function secret(string $code): Response
    {
        return $this->json(['secret' => $this->matches->secret($this->find($code), $this->getUser())]);
    }

    #[Route('/match/{code}/ready', name: 'ready', methods: ['POST'])]
    #[Csrf('game-match-{code}')]
    public function ready(string $code): Response
    {
        $this->matches->ready($this->find($code), $this->getUser());

        return $this->json(['ok' => true]);
    }

    #[Route('/match/{code}/vote', name: 'vote', methods: ['POST'])]
    #[Csrf('game-match-{code}')]
    public function vote(string $code, Request $request): Response
    {
        $this->matches->vote(
            $this->find($code),
            $this->getUser(),
            ($request->toArray()['replay'] ?? false) === true
        );

        return $this->json(['ok' => true]);
    }

    #[Route('/match/{code}/leave', name: 'leave', methods: ['POST'])]
    #[Csrf('game-match-{code}')]
    public function leave(string $code): Response
    {
        $match = $this->find($code);
        $this->matches->leave($match, $this->getUser());

        return $this->redirectToRoute('site_game_show', ['slug' => $match->getGame()->getSlug()]);
    }

    protected function sync(Game $game, GameManager $manager): Game
    {
        try {
            return $manager->install($game->getDirectory());
        } catch (GameManifestException) {
            return $game;
        }
    }

    protected function isPreview(Request $request): bool
    {
        return $request->query->get('preview') === '1' && $this->isGranted('ROLE_ADMIN');
    }

    protected function find(string $code): GameMatch
    {
        return $this->repository->findOneBy(['code' => $code]) ?? throw $this->createNotFoundException();
    }
}