<?php

namespace App\Shared\Listener;

use App\Entity\User;
use App\Shared\Content\ContentTranslationManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Kernel\Event\ControllerEvent;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;

class UserLocaleListener
{
    public function __construct(protected SecurityManagerInterface $security, protected TranslationManagerInterface $translator, protected ContentTranslationManager $contents)
    {
    }

    #[AsListener(priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        $this->apply($event->getRequest());
    }

    #[AsListener(priority: 0)]
    public function onController(ControllerEvent $event): void
    {
        $this->apply($event->getRequest());
    }

    protected function apply(Request $request): void
    {
        $user = $this->security->getUser();
        $locale = $user instanceof User ? $user->getPreferences()->get('game', 'locale') : null;

        if ($locale === null || $request->query->has('lang') || !in_array($locale, $this->contents->available(), true)) {
            return;
        }

        $this->translator->setLocale($locale);
        $request->attributes->set(TranslationManagerInterface::ATTRIBUTE, $locale);
    }
}
