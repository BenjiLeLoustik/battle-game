<?php

namespace App\Site\Account;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Shared\Mail\MailManager;
use App\Shared\Translation\Translator;
use DateTime;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Security\Hasher\UserPasswordHasher;

class PasswordResetManager
{
    private const LIFETIME = '+2 hours';

    public function __construct(
        protected UserRepository $users,
        protected UserPasswordHasher $hasher,
        protected MailManager $mail,
        protected RoutingManagerInterface $router,
    ) {
    }

    public function request(string $email): void
    {
        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);

        if ($user !== null && !$user->isGuest() && $user->getDeletionRequestedAt() === null) {
            $this->send($user);
        }
    }

    public function send(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $this->users->save($user->setReset(hash('sha256', $token), new DateTime(self::LIFETIME)), true);
        $locale = $this->mail->locale($user);
        $this->mail->compose(
            (string) $user->getEmail(),
            Translator::trans('site.reset.mail.subject', [], 'messages', $locale),
            Translator::trans('site.reset.mail.title', [], 'messages', $locale),
            Translator::trans('site.reset.mail.text', ['name' => $user->getUsername()], 'messages', $locale),
            ['label' => Translator::trans('site.reset.mail.action', [], 'messages', $locale), 'url' => $this->router->generate('site_security_reset', ['token' => $token], true)],
            Translator::trans('site.reset.mail.ignore', [], 'messages', $locale),
            null,
            $locale,
        );
    }

    public function find(string $token): ?User
    {
        $user = $this->users->findOneBy(['resetToken' => hash('sha256', $token)]);

        return $user !== null && $user->getResetExpiresAt() > new DateTime() ? $user : null;
    }

    public function reset(User $user, string $password, string $confirmation): ?string
    {
        if (mb_strlen($password) < 10 || mb_strlen($password) > 4096) {
            return 'user.password.too_short';
        }

        if ($password !== $confirmation) {
            return 'account.password.mismatch';
        }

        $user->setPassword($this->hasher->hashPassword($user, $password))->setReset(null, null);
        $this->users->save($user, true);

        return null;
    }
}
