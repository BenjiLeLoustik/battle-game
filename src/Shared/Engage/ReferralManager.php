<?php

namespace App\Shared\Engage;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\WalletManager;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Component\Session\SessionManagerInterface;

class ReferralManager
{
    public const SESSION = '_battlegame.referral';

    public function __construct(
        protected UserRepository $users,
        protected SessionManagerInterface $session,
        protected WalletManager $wallets,
        protected NotificationManager $notifications,
        protected SettingManager $settings,
    ) {
    }

    public function capture(?string $username): void
    {
        $referrer = $username !== null && $username !== '' ? $this->users->findOneBy(['username' => $username]) : null;

        if ($referrer !== null && !$referrer->isGuest()) {
            $this->session->set(self::SESSION, $referrer->getId());
        }
    }

    public function attach(User $user): void
    {
        $id = (int) $this->session->get(self::SESSION, 0);
        $referrer = $id > 0 ? $this->users->find($id) : null;

        if ($referrer !== null && $referrer->getId() !== $user->getId() && $user->getReferredBy() === null) {
            $this->users->save($user->setReferredBy($referrer), true);
        }

        $this->session->remove(self::SESSION);
    }

    public function onMatch(User $user): void
    {
        $referrer = $user->getReferredBy();

        if ($referrer === null || $user->isGuest() || $user->getReferralRewardedAt() !== null) {
            return;
        }

        $this->users->save($user->setReferralRewardedAt(new DateTime()), true);
        $this->wallets->credit($user, (int) $this->settings->get('referral_referee'), 'referral', $referrer->getUsername());
        $this->wallets->credit($referrer, (int) $this->settings->get('referral_referrer'), 'referral', (string) $user->getUsername());
        $this->notifications->notify($referrer, NotificationTypeEnum::REFERRAL_REWARD, ['name' => $user->getUsername(), 'coins' => (int) $this->settings->get('referral_referrer')]);
    }

    public function stats(User $user): array
    {
        $connection = $this->users->getOrm()->getConnection();

        return [
            'invited' => (int) $connection->fetchOne('SELECT COUNT(*) FROM `user` WHERE referred_by_id = :id', ['id' => $user->getId()]),
            'rewarded' => (int) $connection->fetchOne('SELECT COUNT(*) FROM `user` WHERE referred_by_id = :id AND referral_rewarded_at IS NOT NULL', ['id' => $user->getId()]),
            'referrer' => (int) $this->settings->get('referral_referrer'),
            'referee' => (int) $this->settings->get('referral_referee'),
        ];
    }
}
