<?php

namespace App\Controller\Site;

use App\Entity\Clan;
use App\Entity\ClanMember;
use App\Entity\Tournament;
use App\Entity\User;
use App\Repository\ClanMemberRepository;
use App\Repository\ClanRepository;
use App\Repository\ClanRequestRepository;
use App\Repository\GameMatchRepository;
use App\Repository\GameRepository;
use App\Repository\TournamentRepository;
use App\Shared\Clan\ClanManager;
use App\Shared\Clan\Enum\ClanRoleEnum;
use App\Shared\Engage\RatingManager;
use App\Shared\Engage\RewardManager;
use App\Shared\Engage\SeasonPassManager;
use App\Shared\Engage\TournamentManager;
use App\Shared\Exception\BattleGameException;
use App\Shared\Game\Enum\GameMatchModeEnum;
use DateTime;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

class EngageController extends AbstractController
{
    #[Route('/pass', name: 'pass_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function pass(SeasonPassManager $passes): Response
    {
        $user = $this->member();

        return $this->render('@site/pages/engage/pass.html.twig', ['active' => 'pass', 'pass' => $user !== null ? $passes->status($user) : null]);
    }

    #[Route('/pass/claim/{tier}/{track}', name: 'pass_claim', methods: ['POST'], requirements: ['tier' => '\d+', 'track' => '[a-z0-9_]{1,20}'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('pass')]
    public function claim(int $tier, string $track, SeasonPassManager $passes): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        try {
            $passes->claim($user, $tier, $track);
            $this->addFlash('success', 'site.pass.claimed');
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->getTranslationKey());
        }

        return $this->redirectToRoute('site_pass_index');
    }

    #[Route('/ranked', name: 'ranked_index', methods: ['GET'])]
    public function ranked(Request $request, GameRepository $games, RatingManager $ratings): Response
    {
        $list = array_values(array_filter($games->findLive(), fn ($game): bool => $game->getMaxPlayers() >= 2));
        $slug = (string) $request->query->get('game');
        $game = current(array_filter($list, fn ($item): bool => $item->getSlug() === $slug)) ?: ($list[0] ?? null);
        $user = $this->member();
        $mine = $game !== null && $user !== null ? $ratings->rating($user, $game) : null;

        return $this->render('@site/pages/engage/ranked.html.twig', [
            'active' => 'ranked',
            'games' => $list,
            'game' => $game,
            'rows' => $game !== null ? $ratings->leaderboard($game) : [],
            'mine' => $mine,
            'mine_tier' => $mine !== null ? $ratings->tier($mine->getRating()) : null,
            'tiers' => $ratings->tiers(),
            'step' => $ratings->step(),
        ]);
    }

    #[Route('/tournaments', name: 'tournament_index', methods: ['GET'])]
    public function tournaments(TournamentManager $manager, RewardManager $rewards): Response
    {
        $list = $manager->visible();

        return $this->render('@site/pages/engage/tournaments.html.twig', [
            'active' => 'tournaments',
            'tournaments' => array_map(fn (Tournament $tournament): array => [
                'tournament' => $tournament,
                'count' => $manager->count($tournament),
                'joined' => $manager->entry($tournament, $this->member()) !== null,
                'first' => implode(' + ', array_column($rewards->byPlace($tournament->getRewards())[1] ?? [], 'label')),
            ], $list),
        ]);
    }

    #[Route('/tournaments/{id}', name: 'tournament_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function tournament(int $id, TournamentRepository $tournaments, TournamentManager $manager, RewardManager $rewards): Response
    {
        $tournament = $tournaments->find($id);

        if ($tournament === null || (!$tournament->isPublished() && !$this->isGranted('ROLE_ADMIN'))) {
            throw $this->createNotFoundException();
        }

        return $this->render('@site/pages/engage/tournament.html.twig', [
            'active' => 'tournaments',
            'tournament' => $tournament,
            'standings' => $manager->standings($tournament),
            'count' => $manager->count($tournament),
            'joined' => $manager->entry($tournament, $this->member()) !== null,
            'me' => $this->member()?->getId(),
            'pool' => $tournament->getPool(),
            'rewards' => $rewards->byPlace($tournament->getRewards()),
            'qualified' => $tournament->isRestricted() ? $manager->qualified($tournament) : [],
            'eligible' => $manager->isQualified($tournament, $this->member()),
        ]);
    }

    #[Route('/tournaments/{id}/{action}', name: 'tournament_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'join|leave'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('tournaments')]
    public function tournamentAction(int $id, string $action, TournamentRepository $tournaments, TournamentManager $manager): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $tournament = $tournaments->find($id) ?? throw $this->createNotFoundException();

        try {
            $action === 'join' ? $manager->register($tournament, $user) : $manager->unregister($tournament, $user);
            $this->addFlash('success', 'site.tournaments.flash.' . $action);
        } catch (BattleGameException $exception) {
            $this->addFlash('error', $exception->getTranslationKey());
        }

        return $this->redirectToRoute('site_tournament_show', ['id' => $id]);
    }

    #[Route('/clans', name: 'clan_index', methods: ['GET'])]
    public function clans(ClanManager $clans): Response
    {
        $user = $this->member();

        return $this->render('@site/pages/engage/clans.html.twig', [
            'active' => 'clans',
            'ranking' => $clans->ranking(),
            'membership' => $user !== null ? $clans->membership($user) : null,
            'max' => ClanManager::MAX_MEMBERS,
        ]);
    }

    #[Route('/clans/new', name: 'clan_new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('clans')]
    public function newClan(Request $request, ClanManager $clans): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();

        if ($clans->membership($user) !== null) {
            return $this->redirectToRoute('site_clan_show', ['tag' => $clans->membership($user)->getClan()->getTag()]);
        }

        $error = null;

        if ($request->getMethod() === 'POST') {
            try {
                $clan = $clans->create($user, (string) $request->request->get('name'), (string) $request->request->get('tag'), (string) $request->request->get('description'), (string) $request->request->get('color'), $request->request->get('open') !== null);

                return $this->redirectToRoute('site_clan_show', ['tag' => $clan->getTag()]);
            } catch (BattleGameException) {
                $error = 'site.clans.form.error';
            }
        }

        return $this->render('@site/pages/engage/clan_form.html.twig', ['active' => 'clans', 'error' => $error, 'values' => $request->request->all(), 'user' => $user], $error === null ? 200 : 422);
    }

    #[Route('/clans/{tag}', name: 'clan_show', methods: ['GET'], requirements: ['tag' => '[A-Za-z0-9]{2,5}'])]
    public function clan(string $tag, ClanRepository $repository, ClanManager $clans): Response
    {
        $clan = $repository->findOneBy(['tag' => strtoupper($tag)]) ?? throw $this->createNotFoundException();
        $user = $this->member();
        $membership = $user !== null ? $clans->membership($user) : null;
        $mine = $membership !== null && $membership->getClan()->getId() === $clan->getId() ? $membership : null;
        $points = $clans->points($clan);
        $members = $clans->list($clan);
        $weight = ['owner' => 0, 'officer' => 1, 'member' => 2];
        usort($members, fn ($a, $b): int => [$weight[$a->getRole()->value], -(int) ($points[$b->getUser()->getId()] ?? 0)] <=> [$weight[$b->getRole()->value], -(int) ($points[$a->getUser()->getId()] ?? 0)]);
        $rank = array_search($clan->getId(), array_map('intval', array_column($clans->ranking(500), 'id')), true);

        return $this->render('@site/pages/engage/clan.html.twig', [
            'active' => 'clans',
            'clan' => $clan,
            'members' => $members,
            'points' => $points,
            'total' => array_sum(array_map('intval', $points)),
            'rank' => $rank === false ? null : $rank + 1,
            'max' => ClanManager::MAX_MEMBERS,
            'mine' => $mine,
            'other' => $membership !== null && $mine === null,
            'requests' => $mine !== null && $mine->getRole()->canManage() ? $clans->pending($clan) : [],
            'requested' => $user !== null && $mine === null && array_filter($clans->pending($clan), fn ($request): bool => $request->getUser()->getId() === $user->getId()) !== [],
            'roles' => ClanRoleEnum::cases(),
        ]);
    }

    #[Route('/clans/{tag}/{action}', name: 'clan_action', methods: ['POST'], requirements: ['tag' => '[A-Za-z0-9]{2,5}', 'action' => 'join|leave|update|disband'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('clans')]
    public function clanAction(string $tag, string $action, Request $request, ClanRepository $repository, ClanManager $clans): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $clan = $repository->findOneBy(['tag' => strtoupper($tag)]) ?? throw $this->createNotFoundException();
        $membership = $clans->membership($user);
        $mine = $membership !== null && $membership->getClan()->getId() === $clan->getId() ? $membership : null;

        try {
            match ($action) {
                'join' => $this->addFlash('success', $clans->join($clan, $user) ? 'site.clans.flash.joined' : 'site.clans.flash.requested'),
                'leave' => $mine !== null ? $clans->leave($user) : null,
                'update' => $mine?->getRole()->canManage() ? $clans->update($clan, (string) $request->request->get('description'), (string) $request->request->get('color'), $request->request->get('open') !== null) : throw $this->createAccessDeniedException(),
                'disband' => $mine?->getRole() === ClanRoleEnum::OWNER ? $clans->disband($clan) : throw $this->createAccessDeniedException(),
            };
        } catch (BattleGameException) {
            $this->addFlash('error', 'site.clans.flash.error');
        }

        return in_array($action, ['leave', 'disband'], true) ? $this->redirectToRoute('site_clan_index') : $this->redirectToRoute('site_clan_show', ['tag' => $clan->getTag()]);
    }

    #[Route('/clans/{tag}/members/{id}/{action}', name: 'clan_member', methods: ['POST'], requirements: ['tag' => '[A-Za-z0-9]{2,5}', 'id' => '\d+', 'action' => 'kick|officer|member|owner'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('clans')]
    public function clanMember(string $tag, int $id, string $action, ClanManager $clans, ClanMemberRepository $members): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $actor = $clans->membership($user);
        $target = $members->find($id);

        if ($actor === null || $target === null || $actor->getClan()->getId() !== $target->getClan()->getId()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $action === 'kick' ? $clans->kick($actor, $target) : $clans->promote($actor, $target, ClanRoleEnum::from($action));
        } catch (BattleGameException) {
            $this->addFlash('error', 'site.clans.flash.error');
        }

        return $this->redirectToRoute('site_clan_show', ['tag' => $actor->getClan()->getTag()]);
    }

    #[Route('/clans/{tag}/requests/{id}/{answer}', name: 'clan_request', methods: ['POST'], requirements: ['tag' => '[A-Za-z0-9]{2,5}', 'id' => '\d+', 'answer' => 'accept|decline'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('clans')]
    public function clanRequest(string $tag, int $id, string $answer, ClanManager $clans, ClanRequestRepository $requests): Response
    {
        $user = $this->member() ?? throw $this->createAccessDeniedException();
        $actor = $clans->membership($user);
        $request = $requests->find($id);

        if ($actor === null || $request === null || !$actor->getRole()->canManage() || $request->getClan()->getId() !== $actor->getClan()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $clans->answer($request, $answer === 'accept');

        return $this->redirectToRoute('site_clan_show', ['tag' => $actor->getClan()->getTag()]);
    }

    #[Route('/live', name: 'live_index', methods: ['GET'])]
    public function live(GameMatchRepository $matches): Response
    {
        $since = (new DateTime('-3 minutes'))->format('Y-m-d H:i:s');
        $ids = $matches->getOrm()->getConnection()->fetchFirstColumn(
            "SELECT m.id FROM game_match m LEFT JOIN room r ON r.id = m.room_id WHERE m.status = 'playing' AND m.mode IN ('quick', 'ranked', 'room') AND m.updated_at >= :since AND (m.room_id IS NULL OR r.visibility = 'public') ORDER BY m.id DESC LIMIT 40",
            ['since' => $since],
        );

        return $this->render('@site/pages/engage/live.html.twig', [
            'active' => 'live',
            'matches' => array_values(array_filter(array_map(fn ($id) => $matches->find((int) $id), $ids))),
        ]);
    }

    protected function member(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User && !$user->isGuest() ? $user : null;
    }
}
