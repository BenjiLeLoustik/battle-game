<?php

namespace App\Shared\Listener;

use App\Entity\User;
use App\Shared\Moderation\ModerationManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Component\View\ViewManagerInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener(priority: -8)]
class SanctionListener
{
    private const ALLOWED = ['/logout', '/_wdt', '/_profiler', '/builds'];

    public function __construct(
        protected ModerationManager $moderation,
        protected SecurityManagerInterface $security,
        protected ViewManagerInterface $views,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        $path = $event->getRequest()->getPath();

        if (!$user instanceof User || array_filter(self::ALLOWED, fn (string $prefix): bool => str_starts_with($path, $prefix)) !== []) {
            return;
        }

        $sanction = $this->moderation->lockout($user);

        if ($sanction !== null) {
            $event->setResponse(new Response($this->views->render('@site/system/suspended.html.twig', ['sanction' => $sanction]), 403));
        }
    }
}
