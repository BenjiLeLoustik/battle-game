<?php

namespace App\Site\Security;

use App\Repository\UserRepository;
use App\Shared\User\UserManager;
use App\Site\Security\Exception\DiscordRegistrationRequiredException;
use NeoPHP\Package\Security\Contract\OAuthUserResolverInterface;
use NeoPHP\Package\Security\Contract\UserInterface;
use NeoPHP\Package\Security\Exception\CustomUserMessageAuthenticationException;
use NeoPHP\Package\Security\OAuth\OAuthUser;

class DiscordUserResolver implements OAuthUserResolverInterface
{
    public function __construct(
        protected UserRepository $users,
        protected UserManager $manager,
        protected DiscordRegistrationManager $registration,
    ) {
    }

    public function resolve(OAuthUser $user): UserInterface
    {
        $linking = $this->registration->linking();
        $account = $this->users->findOneBy(['discordId' => $user->id]);

        if ($linking !== null) {
            $current = $this->users->find($linking);

            if ($current === null || ($account !== null && $account->getId() !== $current->getId())) {
                throw new CustomUserMessageAuthenticationException('This Discord account is already linked to another player.');
            }

            return $this->manager->linkDiscord($current, $user);
        }

        if ($account !== null) {
            return $account;
        }

        if ($user->email === null || ($user->raw['verified'] ?? false) !== true) {
            throw new CustomUserMessageAuthenticationException('Your Discord email address must be verified.');
        }

        $account = $this->users->findOneBy(['email' => mb_strtolower($user->email)]);

        if ($account !== null) {
            return $this->manager->linkDiscord($account, $user);
        }

        $this->registration->start($user);

        throw new DiscordRegistrationRequiredException();
    }
}