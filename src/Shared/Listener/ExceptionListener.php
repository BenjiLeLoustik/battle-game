<?php

namespace App\Shared\Listener;

use App\Shared\Exception\BattleGameException;
use App\Shared\Translation\ExceptionTranslation;
use NeoPHP\Component\Event\Attribute\AsListener;
use NeoPHP\Component\Http\Response\JsonResponse;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Kernel\Event\ExceptionEvent;
use NeoPHP\Component\View\ViewManagerInterface;

#[AsListener]
class ExceptionListener
{
    public function __construct(
        private ViewManagerInterface $view,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($event->getKernel()->isDebug() && !$exception instanceof BattleGameException) {
            return;
        }

        $status = ExceptionTranslation::status($exception);
        $message = ExceptionTranslation::translate($exception);
        $request = $event->getRequest();

        if ($request->wantsJson()) {
            $event->setResponse(
                new JsonResponse([
                    'error' => $message
                ], $status)
            );

            return;
        }

        $space = str_starts_with($request->getPath(), '/admin') ? 'admin' : 'site';
        $html = $this->view->render('@' . $space . '/system/error.html.twig', [
            'status' => $status,
            'message' => $message,
        ]);

        $event->setResponse(new Response($html, $status));
    }
}