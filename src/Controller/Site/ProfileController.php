<?php

namespace App\Controller\Site;

use App\Shared\Clan\ClanManager;
use App\Shared\Engage\RatingManager;
use App\Shared\Social\PresenceManager;
use DateTime;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\FriendManager;
use App\Site\Profile\ProfileManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route(name: 'profile_')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function me(): Response
    {
        $user = $this->getUser();

        return $user->isGuest()
            ? $this->redirectToRoute('site_security_register')
            : $this->redirectToRoute('site_profile_show', ['username' => $user->getUsername()]);
    }

    #[Route('/players/{username}', name: 'show', methods: ['GET'], requirements: ['username' => '[A-Za-z0-9_]{3,30}'])]
    public function show(string $username, UserRepository $users, ProfileManager $profiles, FriendManager $friends, RatingManager $ratings, ClanManager $clans, PresenceManager $presence): Response
    {
        $user = $users->findOneBy(['username' => $username]);

        if ($user === null || $user->isGuest()) {
            throw $this->createNotFoundException();
        }

        if (!$profiles->isVisible($user, $this->getUser())) {
            return $this->render('@site/pages/profile/private.html.twig', ['user' => $user], 403);
        }

        $viewer = $this->getUser();
        $social = $viewer instanceof User && !$viewer->isGuest() && $viewer->getId() !== $user->getId();

        $connection = $users->getOrm()->getConnection();
        $days = $connection->fetchAllKeyValue('SELECT DATE(created_at) AS day, SUM(points) FROM game_result WHERE user_id = :id AND created_at >= :since GROUP BY DATE(created_at)', ['id' => $user->getId(), 'since' => (new DateTime('-29 days'))->format('Y-m-d 00:00:00')]);
        $activity = [];

        for ($offset = 29; $offset >= 0; $offset--) {
            $day = (new DateTime('-' . $offset . ' days'))->format('Y-m-d');
            $activity[] = ['day' => $day, 'points' => (int) ($days[$day] ?? 0)];
        }

        $isFriend = $social && $friends->state($viewer, $user) === 'friends';

        return $this->render('@site/pages/profile/show.html.twig', $profiles->build($user, $viewer) + [
            'activity' => $activity,
            'activityMax' => max(1, ...array_column($activity, 'points')),
            'ratings' => $ratings->best($user),
            'clan' => $clans->membership($user),
            'presence' => $isFriend ? $presence->status($user) : null,
            'social' => $social ? [
                'state' => $friends->state($viewer, $user),
                'blocked' => $friends->hasBlocked($viewer, $user),
                'room' => $friends->currentRoom($viewer),
                'reasons' => ReportReasonEnum::cases(),
            ] : null,
        ]);
    }
}
