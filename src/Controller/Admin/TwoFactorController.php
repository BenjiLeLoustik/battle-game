<?php

namespace App\Controller\Admin;

use App\Admin\Security\TotpManager;
use App\Entity\User;
use App\Shared\Setting\SettingManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Component\Session\SessionManagerInterface;

#[Route(name: 'twofa_')]
class TwoFactorController extends AbstractAdminController
{
    public function __construct(
        protected TotpManager $totp,
        protected SessionManagerInterface $session,
        protected SettingManager $settings,
    ) {
    }

    #[Route('/security', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->admin();
        $secret = null;

        if (!$user->hasTotp()) {
            $secret = $this->session->get(TotpManager::PENDING) ?? $this->totp->secret();
            $this->session->set(TotpManager::PENDING, $secret);
        }

        return $this->render('@admin/pages/security/index.html.twig', [
            'user' => $user,
            'secret' => $secret,
            'qr' => $secret !== null ? $this->totp->qr($user, $secret) : null,
            'codes' => $this->session->get('_battlegame.admin_2fa_codes'),
            'remaining' => count((array) $user->getTotpRecovery()),
            'required' => (bool) $this->settings->get('admin_2fa_required'),
            'manage' => $this->can('sys.admins'),
        ]);
    }

    #[Route('/security/enable', name: 'enable', methods: ['POST'])]
    #[Csrf('admin-2fa')]
    public function enable(Request $request): Response
    {
        $user = $this->admin();
        $secret = (string) $this->session->get(TotpManager::PENDING);

        if ($secret === '' || !$this->totp->check($secret, (string) $request->request->get('code'))) {
            $this->addFlash('error', 'admin.twofa.invalid');

            return $this->redirectToRoute('admin_twofa_index');
        }

        $codes = $this->totp->enable($user, $secret);
        $this->session->remove(TotpManager::PENDING);
        $this->session->set('_battlegame.admin_2fa_codes', $codes);
        $this->audit('twofa.enable');

        return $this->done('admin_twofa_index', [], 'admin.twofa.enabled');
    }

    #[Route('/security/codes/seen', name: 'seen', methods: ['POST'])]
    #[Csrf('admin-2fa')]
    public function seen(): Response
    {
        $this->session->remove('_battlegame.admin_2fa_codes');

        return $this->redirectToRoute('admin_twofa_index');
    }

    #[Route('/security/disable', name: 'disable', methods: ['POST'])]
    #[Csrf('admin-2fa')]
    public function disable(Request $request): Response
    {
        $user = $this->admin();

        if (!$this->totp->check((string) $user->getTotpSecret(), (string) $request->request->get('code'))) {
            $this->addFlash('error', 'admin.twofa.invalid');

            return $this->redirectToRoute('admin_twofa_index');
        }

        $this->totp->disable($user);
        $this->audit('twofa.disable');

        return $this->done('admin_twofa_index', [], 'admin.twofa.disabled');
    }

    #[Route('/security/required', name: 'required', methods: ['POST'])]
    #[Csrf('admin-2fa')]
    public function required(Request $request): Response
    {
        $this->allow('sys.admins');
        $this->settings->set('admin_2fa_required', $request->request->get('required') !== null);
        $this->audit('twofa.required');

        return $this->done('admin_twofa_index');
    }

    #[Route('/2fa', name: 'verify', methods: ['GET', 'POST'])]
    #[Csrf('admin-2fa')]
    public function verify(Request $request): Response
    {
        $user = $this->admin();
        $target = (string) ($request->query->get('to') ?? '/admin');
        $target = str_starts_with($target, '/admin') && !str_starts_with($target, '//') ? $target : '/admin';

        if (!$user->hasTotp() || $this->totp->isVerified($user)) {
            return $this->redirect($target);
        }

        $error = null;

        if ($request->getMethod() === 'POST') {
            if ($this->totp->verify($user, (string) $request->request->get('code'))) {
                return $this->redirect($target);
            }

            $error = 'admin.twofa.invalid';
        }

        return $this->render('@admin/pages/security/verify.html.twig', ['error' => $error, 'target' => $target], $error === null ? 200 : 422);
    }

    protected function admin(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
