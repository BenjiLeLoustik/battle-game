<?php

namespace App\Controller\Site;

use App\Shared\Engage\ReferralManager;
use App\Entity\User;
use App\Shared\Setting\SettingManager;
use App\Shared\User\UserManager;
use App\Site\Account\PasswordResetManager;
use App\Site\Security\DiscordRegistrationManager;
use App\Site\Security\Form\DiscordRegisterForm;
use App\Site\Security\Form\GuestForm;
use App\Site\Security\Form\RegisterForm;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Form\Contract\FormInterface;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\Security\Authenticator\FormLoginAuthenticator;
use NeoPHP\Package\Security\OAuth\OAuthManager;

#[Route(name: 'security_')]
class SecurityController extends AbstractController
{
    public function __construct(protected SettingManager $settings)
    {
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        if ($this->isMember()) {
            return $this->redirectToRoute('site_home_index');
        }

        return $this->render('@site/pages/security/login.html.twig', [
            'last_username' => $this->getLastUsername(),
            'error' => $this->getLastAuthenticationError(),
            'guest' => $this->guestForm(),
        ]);
    }

    #[Route('/guest', name: 'guest', methods: ['POST'])]
    public function guest(Request $request, UserManager $users, SessionManagerInterface $session): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('site_home_index');
        }

        $form = $this->guestForm();

        if ($form === null) {
            return $this->redirectToRoute('site_security_login');
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->loginUser($users->createGuest($form->get('username')->getData()), null, true);
            $target = $session->remove(FormLoginAuthenticator::targetPathKey('main'));

            return is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//')
                ? $this->redirect($target)
                : $this->redirectToRoute('site_home_index');
        }

        return $this->render('@site/pages/security/login.html.twig', [
            'last_username' => '',
            'error' => null,
            'guest' => $form,
        ], 422);
    }

    #[Route('/forgot-password', name: 'forgot', methods: ['GET', 'POST'])]
    #[Csrf('forgot')]
    public function forgot(Request $request, PasswordResetManager $resets): Response
    {
        $sent = $request->getMethod() === 'POST';

        if ($sent) {
            $resets->request((string) $request->request->get('email'));
        }

        return $this->render('@site/pages/security/forgot.html.twig', ['sent' => $sent, 'guest' => null]);
    }

    #[Route('/reset-password/{token}', name: 'reset', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    #[Csrf('reset')]
    public function reset(string $token, Request $request, PasswordResetManager $resets): Response
    {
        $user = $resets->find($token);
        $error = null;

        if ($user !== null && $request->getMethod() === 'POST') {
            $error = $resets->reset($user, (string) $request->request->get('password'), (string) $request->request->get('confirmation'));

            if ($error === null) {
                $this->loginUser($user, null, false);

                return $this->redirectToRoute('site_home_index');
            }
        }

        return $this->render('@site/pages/security/reset.html.twig', ['user' => $user, 'error' => $error, 'guest' => null], $user === null || $error !== null ? 422 : 200);
    }

    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserManager $users): Response
    {
        if ($this->isMember()) {
            return $this->redirectToRoute('site_home_index');
        }

        $user = $this->getUser() ?? new User();

        if ($user->isGuest() && str_ends_with((string) $user->getEmail(), '@' . User::GUEST_DOMAIN)) {
            $user->setEmail('');
        }

        if (!$this->settings->get('registration')) {
            return $this->render('@site/pages/security/closed.html.twig', ['guest' => $this->guestForm()], 403);
        }

        $form = $this->createForm(RegisterForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $users->register($user, $form->get('plainPassword')->getData());
            $this->get(ReferralManager::class)->attach($user);
            $this->loginUser($user, null, true);

            return $this->redirectToRoute('site_home_index');
        }

        return $this->render('@site/pages/security/register.html.twig', [
            'form' => $form,
            'guest' => $user->isGuest() ? null : $this->guestForm(),
        ]);
    }

    protected function guestForm(): ?FormInterface
    {
        if (!$this->settings->get('guests')) {
            return null;
        }

        return $this->createForm(GuestForm::class, null, ['action' => $this->generateUrl('site_security_guest')]);
    }

    protected function isMember(): bool
    {
        return $this->getUser() !== null && !$this->getUser()->isGuest();
    }

    #[Route('/register/discord', name: 'discord_register', methods: ['GET', 'POST'])]
    public function discordRegister(Request $request, DiscordRegistrationManager $registration): Response
    {
        $discord = $registration->pending();

        if ($this->getUser() !== null || $discord === null) {
            return $this->redirectToRoute('site_security_login');
        }

        if (!$this->settings->get('registration')) {
            return $this->render('@site/pages/security/closed.html.twig', ['guest' => $this->guestForm()], 403);
        }

        $form = $this->createForm(DiscordRegisterForm::class, ['username' => $registration->suggestedUsername($discord)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $registration->complete($discord, $form->get('username')->getData());
            $this->get(ReferralManager::class)->attach($user);
            $this->loginUser($user, null, true);

            return $this->redirectToRoute('site_home_index');
        }

        return $this->render('@site/pages/security/discord.html.twig', [
            'form' => $form,
            'name' => $discord->raw['global_name'] ?? $discord->raw['username'] ?? '',
        ]);
    }

    #[Route('/oauth/discord', name: 'discord', methods: ['GET'])]
    public function discord(OAuthManager $oauth): Response
    {
        return $oauth->redirect('discord');
    }
}