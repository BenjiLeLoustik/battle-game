<?php

namespace App\Shared\Listener;

use App\Shared\Maintenance\Exception\FeatureUnavailableException;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Translation\Translator;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\JsonResponse;
use NeoPHP\Component\Http\Response\RedirectResponse;
use NeoPHP\Component\Kernel\Event\RequestEvent;
use NeoPHP\Package\Security\SecurityManagerInterface;

#[AsListener]
class MaintenanceListener
{
    public function __construct(protected MaintenanceManager $maintenance, protected SecurityManagerInterface $security)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPath();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $site = $this->maintenance->site();

        if ($site !== null && !$this->maintenance->allows($site, $path)) {
            $event->setResponse($request->wantsJson()
                ? new JsonResponse(['error' => Translator::trans('error.maintenance.site', [], 'errors')], 503)
                : new RedirectResponse('/maintenance'));

            return;
        }

        $feature = $this->maintenance->feature($path);

        if ($feature !== null) {
            throw new FeatureUnavailableException(['message' => $feature->getMessage()]);
        }
    }
}
