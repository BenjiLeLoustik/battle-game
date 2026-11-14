<?php

namespace App\Controller\Site;

use App\Repository\UserRepository;
use App\Shared\Mail\MailManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/emails', name: 'email_')]
class EmailController extends AbstractController
{
    #[Route('/unsubscribe/{id}/{token}', name: 'unsubscribe', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function unsubscribe(int $id, string $token, Request $request, UserRepository $users, MailManager $mail): Response
    {
        $user = $users->find($id);
        $valid = $user !== null && hash_equals($mail->token($user), $token);
        $done = false;

        if ($valid && $request->getMethod() === 'POST') {
            $notifications = $user->getPreferences()->section('notifications');
            $notifications['events']['email'] = false;
            $user->setPreferences($user->getPreferences()->with('notifications', $notifications));
            $users->save($user, true);
            $done = true;
        }

        return $this->render('@site/pages/email/unsubscribe.html.twig', [
            'valid' => $valid,
            'done' => $done || ($valid && !$user->getPreferences()->wants('events', 'email')),
        ]);
    }
}
