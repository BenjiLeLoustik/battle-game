<?php

namespace App\Site\Security;

use App\Entity\User;
use App\Shared\User\UserManager;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\Security\OAuth\OAuthUser;

class DiscordRegistrationManager
{
    public const SESSION_KEY = '_battlegame.discord_registration';
    public const LINK_KEY = '_battlegame.discord_link';

    public function __construct(
        protected SessionManagerInterface $session,
        protected UserManager $users,
    ) {
    }

    public function start(OAuthUser $discord): void
    {
        $this->session->set(self::SESSION_KEY, [
            'id' => $discord->id,
            'email' => $discord->email,
            'raw' => $discord->raw,
        ]);
    }

    public function link(int $user): void
    {
        $this->session->set(self::LINK_KEY, $user);
    }

    public function linking(): ?int
    {
        $user = $this->session->get(self::LINK_KEY);

        return is_int($user) ? $user : null;
    }

    public function finishLinking(): bool
    {
        return $this->session->remove(self::LINK_KEY) !== null;
    }

    public function pending(): ?OAuthUser
    {
        $data = $this->session->get(self::SESSION_KEY);

        return is_array($data)
            ? new OAuthUser('discord', $data['id'], $data['email'], true, null, $data['raw'])
            : null;
    }

    public function suggestedUsername(OAuthUser $discord): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_]/', '', (string) ($discord->raw['username'] ?? '')), 0, 30);
    }

    public function complete(OAuthUser $discord, string $username): User
    {
        $this->session->remove(self::SESSION_KEY);

        return $this->users->registerFromDiscord($discord, $username);
    }
}