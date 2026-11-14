<?php

namespace App\Controller\Site;

use App\Entity\Report;
use App\Entity\User;
use App\Repository\GameResultRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\FriendManager;
use App\Shared\Social\PresenceManager;
use App\Shared\User\LevelManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route(name: 'friend_')]
#[IsGranted('ROLE_USER')]
class FriendController extends AbstractController
{
    private const TABS = ['online', 'all', 'blocked'];
    private const USERNAME = '[A-Za-z0-9_]{3,30}';

    public function __construct(
        protected FriendManager $friends,
        protected UserRepository $users
    ) {
    }

    #[Route('/friends', name: 'index', methods: ['GET'])]
    public function index(Request $request, PresenceManager $presence, GameResultRepository $results, LevelManager $levels): Response
    {
        $user = $this->getUser();
        $friends = $this->friends->friends($user);
        $experience = $results->experience(array_map(fn (User $friend): int => $friend->getId(), $friends));
        $rows = array_map(fn (User $friend): array => [
            'user' => $friend,
            'status' => $presence->status($friend),
            'level' => $levels->level((int) ($experience[$friend->getId()] ?? 0)),
        ], $friends);
        usort($rows, fn (array $a, array $b): int => ($a['status']['state'] === 'offline') <=> ($b['status']['state'] === 'offline'));
        $online = array_values(array_filter($rows, fn (array $row): bool => $row['status']['state'] !== 'offline'));
        $tab = in_array($request->query->get('tab'), self::TABS, true) ? $request->query->get('tab') : ($online === [] ? 'all' : 'online');

        return $this->render('@site/pages/friend/index.html.twig', [
            'tab' => $tab,
            'rows' => $tab === 'online' ? $online : $rows,
            'counts' => ['online' => count($online), 'all' => count($rows)],
            'blocked' => $this->friends->blocked($user),
            'requests' => array_map(fn (User $other): array => ['user' => $other, 'mutual' => $this->friends->mutual($user, $other)], $this->friends->requests($user)),
            'sent' => $this->friends->sent($user),
            'recent' => $this->friends->recentPlayers($user),
            'room' => $this->friends->currentRoom($user),
        ]);
    }

    #[Route('/friends/add', name: 'add', methods: ['POST'])]
    #[Csrf('friends')]
    public function add(Request $request): Response
    {
        $username = ltrim(preg_replace('/#\d+$/', '', trim((string) $request->request->get('username'))), '@');

        return $this->back($request, $this->friends->request($this->getUser(), $this->users->findOneBy(['username' => $username])), 'site.friends.flash.sent');
    }

    #[Route('/friends/{username}/{action}', name: 'action', methods: ['POST'], requirements: ['username' => self::USERNAME, 'action' => 'accept|decline|remove|cancel|block|unblock|invite'])]
    #[Csrf('friends')]
    public function action(string $username, string $action, Request $request): Response
    {
        $user = $this->getUser();
        $other = $this->users->findOneBy(['username' => $username]) ?? throw $this->createNotFoundException();

        $error = match ($action) {
            'accept' => $this->friends->accept($user, $other),
            'invite' => $this->friends->invite($user, $other),
            'block' => $this->friends->block($user, $other),
            'unblock' => $this->friends->unblock($user, $other),
            default => $this->friends->remove($user, $other),
        };

        return $this->back($request, $error, 'site.friends.flash.' . $action);
    }

    #[Route('/players/{username}/report', name: 'report', methods: ['POST'], requirements: ['username' => self::USERNAME])]
    #[Csrf('friends')]
    public function report(string $username, Request $request, ReportRepository $reports): Response
    {
        $other = $this->users->findOneBy(['username' => $username]) ?? throw $this->createNotFoundException();
        $reports->save((new Report())
            ->setReporter($this->getUser())
            ->setReported($other)
            ->setReason(ReportReasonEnum::tryFrom((string) $request->request->get('reason')) ?? ReportReasonEnum::OTHER)
            ->setComment(mb_substr(trim((string) $request->request->get('comment')), 0, 500) ?: null), true);

        return $this->back($request, null, 'site.friends.flash.report');
    }

    protected function back(Request $request, ?string $error, string $success): Response
    {
        $this->addFlash($error === null ? 'success' : 'error', $error ?? $success);
        $target = (string) $request->request->get('_back');

        return str_starts_with($target, '/') && !str_starts_with($target, '//')
            ? $this->redirect($target)
            : $this->redirectToRoute('site_friend_index');
    }
}
