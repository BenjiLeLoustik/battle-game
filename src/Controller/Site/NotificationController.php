<?php

namespace App\Controller\Site;

use App\Shared\Social\NotificationManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/notifications', name: 'notification_')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(protected NotificationManager $notifications)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->json([
            'items' => $this->notifications->latest($this->getUser()),
            'unread' => $this->notifications->unread($this->getUser()),
        ]);
    }

    #[Route('/read', name: 'read', methods: ['POST'])]
    #[Csrf('notifications')]
    public function read(): Response
    {
        $this->notifications->markRead($this->getUser());

        return $this->json(['ok' => true]);
    }
}
