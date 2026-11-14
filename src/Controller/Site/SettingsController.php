<?php

namespace App\Controller\Site;

use App\Site\Account\DataExporter;
use App\Shared\Engage\ReferralManager;
use App\Repository\UserBadgeRepository;
use App\Repository\CountryRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Shared\Badge\BadgeManager;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\User\UserPreferences;
use App\Site\Account\AccountManager;
use App\Site\Account\PreferencesRequest;
use App\Site\Security\DiscordRegistrationManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/settings', name: 'settings_')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        protected AccountManager $accounts,
        protected UserRepository $users,
        protected ContentTranslationManager $contents
    ) {
    }

    #[Route('', name: 'profile', methods: ['GET', 'POST'])]
    #[Csrf('settings')]
    public function profile(Request $request, GameRepository $games, BadgeManager $badges, CountryRepository $countries): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $errors = $this->accounts->updateProfile($this->getUser(), $request->request->all());

            if ($errors === []) {
                return $this->saved('site_settings_profile');
            }
        }

        return $this->page('profile', [
            'errors' => $errors,
            'countries' => $countries->findOrdered(),
            'games' => $games->findLive(),
            'badges' => $badges->obtained($this->getUser()),
            'renameAt' => $this->accounts->canRename($this->getUser()),
        ], $errors === [] ? 200 : 422);
    }

    #[Route('/avatar', name: 'avatar', methods: ['POST'])]
    #[Csrf('settings')]
    public function avatar(Request $request): Response
    {
        $error = match ((string) $request->request->get('mode')) {
            'upload' => $this->accounts->uploadAvatar($this->getUser(), $request->files->get('avatar')),
            'discord' => $this->accounts->useDiscordAvatar($this->getUser()),
            default => $this->accounts->removeAvatar($this->getUser()),
        };

        return $error === null ? $this->saved('site_settings_profile') : $this->failed('site_settings_profile', $error);
    }

    #[Route('/account', name: 'account', methods: ['GET'])]
    public function account(Request $request): Response
    {
        return $this->page('account', [
            'devices' => $this->accounts->devices($this->getUser(), $this->series($request)),
        ]);
    }

    #[Route('/account/email', name: 'email', methods: ['POST'])]
    #[Csrf('settings')]
    public function email(Request $request): Response
    {
        $error = $this->accounts->requestEmail($this->getUser(), (string) $request->request->get('email'), (string) $request->request->get('password'));

        return $error === null ? $this->saved('site_settings_account', 'site.settings.flash.email_sent') : $this->failed('site_settings_account', $error);
    }

    #[Route('/account/email/{token}', name: 'email_confirm', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function confirmEmail(string $token): Response
    {
        if (!$this->accounts->confirmEmail($this->getUser(), $token)) {
            return $this->failed('site_settings_account', 'account.email.token');
        }

        $this->loginUser($this->getUser());

        return $this->saved('site_settings_account', 'site.settings.flash.email_changed');
    }

    #[Route('/account/password', name: 'password', methods: ['POST'])]
    #[Csrf('settings')]
    public function password(Request $request): Response
    {
        $error = $this->accounts->changePassword(
            $this->getUser(),
            (string) $request->request->get('current'),
            (string) $request->request->get('password'),
            (string) $request->request->get('confirmation'),
        );

        if ($error !== null) {
            return $this->failed('site_settings_account', $error);
        }

        $this->loginUser($this->getUser());

        return $this->saved('site_settings_account', 'site.settings.flash.password');
    }

    #[Route('/account/discord', name: 'discord_link', methods: ['POST'])]
    #[Csrf('settings')]
    public function linkDiscord(DiscordRegistrationManager $discord): Response
    {
        $discord->link($this->getUser()->getId());

        return $this->redirectToRoute('site_security_discord');
    }

    #[Route('/account/discord/unlink', name: 'discord_unlink', methods: ['POST'])]
    #[Csrf('settings')]
    public function unlinkDiscord(): Response
    {
        $error = $this->accounts->unlinkDiscord($this->getUser());

        return $error === null ? $this->saved('site_settings_account') : $this->failed('site_settings_account', $error);
    }

    #[Route('/account/devices/others', name: 'devices_others', methods: ['POST'])]
    #[Csrf('settings')]
    public function revokeOthers(Request $request): Response
    {
        $this->accounts->revokeOthers($this->getUser(), $this->series($request));

        return $this->saved('site_settings_account', 'site.settings.flash.devices');
    }

    #[Route('/account/devices/revoke', name: 'device_revoke', methods: ['POST'])]
    #[Csrf('settings')]
    public function revoke(Request $request): Response
    {
        $this->accounts->revokeDevice($this->getUser(), (string) $request->request->get('series'));

        return $this->saved('site_settings_account', 'site.settings.flash.devices');
    }

    #[Route('/account/export', name: 'export', methods: ['GET'])]
    public function export(DataExporter $exporter): Response
    {
        $response = $this->json($this->accounts->export($this->getUser()) + ['all_data' => $exporter->export($this->getUser())]);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="battlegame-%s.json"', $this->getUser()->getUsername()));

        return $response;
    }

    #[Route('/showcase', name: 'showcase', methods: ['POST'])]
    #[Csrf('settings')]
    public function showcase(Request $request, UserBadgeRepository $badges): Response
    {
        $user = $this->getUser();
        $owned = array_keys($badges->obtained($user));
        $chosen = array_slice(array_values(array_intersect(array_map('intval', (array) ($request->request->all()['badges'] ?? [])), $owned)), 0, 3);
        $this->users()->save($user->setShowcase($chosen), true);
        $this->addFlash('success', 'site.settings.showcase.saved');

        return $this->redirectToRoute('site_settings_profile');
    }

    #[Route('/referral', name: 'referral', methods: ['GET'])]
    public function referral(ReferralManager $referrals): Response
    {
        return $this->render('@site/pages/settings/referral.html.twig', [
            'stats' => $referrals->stats($this->getUser()),
            'link' => $this->generateUrl('site_home_index', ['ref' => $this->getUser()->getUsername()], true),
        ]);
    }

    protected function users(): UserRepository
    {
        return $this->get(UserRepository::class);
    }

    #[Route('/privacy', name: 'privacy', methods: ['GET', 'POST'])]
    #[Csrf('settings')]
    public function privacy(Request $request): Response
    {
        return $this->preferences($request, 'privacy');
    }

    #[Route('/game', name: 'game', methods: ['GET', 'POST'])]
    #[Csrf('settings')]
    public function game(Request $request): Response
    {
        return $this->preferences($request, 'game');
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET', 'POST'])]
    #[Csrf('settings')]
    public function notifications(Request $request): Response
    {
        return $this->preferences($request, 'notifications');
    }

    #[Route('/pause', name: 'pause', methods: ['POST'])]
    #[Csrf('settings')]
    public function pause(): Response
    {
        $user = $this->getUser();
        $user->setPreferences($user->getPreferences()->with('privacy', ['public' => false] + $user->getPreferences()->section('privacy')));
        $this->users->save($user, true);

        return $this->saved('site_settings_privacy', 'site.settings.flash.paused');
    }

    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]
    #[Csrf('settings')]
    public function delete(Request $request): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $error = $this->accounts->requestDeletion($this->getUser(), (string) $request->request->get('username'), (string) $request->request->get('password'));
        }

        return $this->page('delete', [
            'error' => $error,
            'deletionDate' => $this->accounts->deletionDate($this->getUser()),
        ], $error === null ? 200 : 422);
    }

    #[Route('/delete/cancel', name: 'delete_cancel', methods: ['POST'])]
    #[Csrf('settings')]
    public function cancelDeletion(): Response
    {
        $this->accounts->cancelDeletion($this->getUser());

        return $this->saved('site_settings_delete', 'site.settings.flash.deletion_cancelled');
    }

    protected function preferences(Request $request, string $section): Response
    {
        if ($request->getMethod() === 'POST') {
            $user = $this->getUser();
            $user->setPreferences($user->getPreferences()->with($section, PreferencesRequest::fromArray($section, $request->request->all(), $this->contents->available())));
            $this->users->save($user, true);

            return $this->saved('site_settings_' . $section);
        }

        return $this->page($section, [
            'audiences' => UserPreferences::AUDIENCES,
            'accents' => UserPreferences::ACCENTS,
            'events' => UserPreferences::EVENTS,
            'channels' => UserPreferences::CHANNELS,
            'locales' => $this->contents->available(),
        ]);
    }

    protected function page(string $section, array $parameters = [], int $status = 200): Response
    {
        return $this->render('@site/pages/settings/' . $section . '.html.twig', $parameters + [
            'section' => $section,
            'preferences' => $this->getUser()->getPreferences(),
        ], $status);
    }

    protected function saved(string $route, string $message = 'site.settings.flash.saved'): Response
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute($route);
    }

    protected function failed(string $route, string $error): Response
    {
        $this->addFlash('error', $error);

        return $this->redirectToRoute($route);
    }

    protected function series(Request $request): ?string
    {
        $parts = explode(':', (string) $request->cookies->get('REMEMBERME'));

        return $parts[1] ?? null;
    }
}
