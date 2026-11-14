<?php

namespace App\Admin\Security;

use App\Entity\User;
use App\Shared\Setting\SettingManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\RedirectResponse;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener(priority: -4)]
class TwoFactorListener
{
    private const OPEN = ['/admin/2fa', '/admin/security'];

    public function __construct(
        protected SecurityManagerInterface $security,
        protected TotpManager $totp,
        protected SettingManager $settings,
        protected RoutingManagerInterface $router,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPath();
        $user = $this->security->getUser();

        if (!str_starts_with($path, '/admin') || !$user instanceof User || !$user->isAdmin()) {
            return;
        }

        foreach (self::OPEN as $open) {
            if ($path === $open || str_starts_with($path, $open . '/')) {
                return;
            }
        }

        if ($user->hasTotp() && !$this->totp->isVerified($user)) {
            $event->setResponse(new RedirectResponse($this->router->generate('admin_twofa_verify', ['to' => $path])));
        } elseif (!$user->hasTotp() && $this->settings->get('admin_2fa_required')) {
            $event->setResponse(new RedirectResponse($this->router->generate('admin_twofa_index')));
        }
    }
}
