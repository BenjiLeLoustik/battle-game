<?php

namespace App\Shared\Listener;

use App\Repository\PageRepository;
use App\Shared\Page\PageManager;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Kernel\Event\ExceptionEvent;
use NeoPHP\Component\Routing\Exception\RouteNotFoundException;
use NeoPHP\Component\View\ViewManagerInterface;

#[AsListener(priority: 10)]
class PageListener
{
    public function __construct(
        protected PageRepository $pages,
        protected PageManager $manager,
        protected ViewManagerInterface $view,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->getThrowable() instanceof RouteNotFoundException || $request->getMethod() !== 'GET') {
            return;
        }

        $page = $this->pages->findPublished(trim($request->getPath(), '/'));

        if ($page === null) {
            return;
        }

        $event->setResponse(new Response($this->view->render('@site/pages/page/show.html.twig', [
            'page' => $page,
            'html' => $this->manager->html($page),
            'style' => $this->manager->style($page),
        ])));
    }
}
