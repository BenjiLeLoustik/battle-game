<?php

namespace App\Shared\User;

use App\Entity\User;
use App\Repository\UserRepository;
use NeoPHP\Package\Security\Hasher\UserPasswordHasher;
use NeoPHP\Package\Security\OAuth\OAuthUser;

class UserManager
{
    private const DISCORD_AVATAR = 'https://cdn.discordapp.com/avatars/%s/%s.png?size=128';

    public function __construct(
        protected UserRepository $users,
        protected UserPasswordHasher $hasher,
    ) {
    }

    public function register(User $user, string $plainPassword): User
    {
        $user->setGuest(false);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->users->save($user, true);

        return $user;
    }

    public function createGuest(string $username): User
    {
        $user = (new User())
            ->setFirstName($username)
            ->setLastName('')
            ->setUsername($username)
            ->setEmail(sprintf('%s@%s', bin2hex(random_bytes(12)), User::GUEST_DOMAIN))
            ->setGuest(true);

        $this->users->save($user, true);

        return $user;
    }

    public function registerFromDiscord(OAuthUser $discord, string $username): User
    {
        $name = trim((string) ($discord->raw['global_name'] ?? '')) ?: $username;
        $parts = preg_split('/\s+/', $name, 2);

        $user = new User();
        $user->setFirstName(mb_substr($parts[0], 0, 50));
        $user->setLastName(mb_substr($parts[1] ?? '', 0, 50));
        $user->setUsername($username);
        $user->setEmail((string) $discord->email);

        return $this->linkDiscord($user, $discord);
    }

    public function linkDiscord(User $user, OAuthUser $discord): User
    {
        $avatar = empty($discord->raw['avatar']) ? null : sprintf(self::DISCORD_AVATAR, $discord->id, $discord->raw['avatar']);
        $user->setDiscordId($discord->id)->setDiscordAvatar($avatar);

        if ($user->getAvatar() === null) {
            $user->setAvatar($avatar);
        }

        $this->users->save($user, true);

        return $user;
    }
}