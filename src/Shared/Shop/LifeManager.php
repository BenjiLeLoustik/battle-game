<?php

namespace App\Shared\Shop;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Exception\NoLivesException;
use DateTime;
use DateTimeZone;

class LifeManager
{
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        protected WalletManager $wallets,
        protected WalletRepository $repository,
        protected SubscriptionManager $subscriptions,
        protected SettingManager $settings,
    ) {
    }

    public function allowance(User $user): ?int
    {
        $subscription = $this->subscriptions->current($user);
        $value = $subscription !== null ? (int) ($subscription->getProduct()?->option('lives') ?? $this->settings->get('lives_free')) : (int) $this->settings->get('lives_free');

        return $value <= 0 ? null : $value;
    }

    public function status(User $user): array
    {
        $wallet = $this->roll($this->wallets->wallet($user));
        $max = $this->allowance($user);

        return [
            'unlimited' => $max === null,
            'max' => $max ?? 0,
            'left' => $max === null ? null : max(0, $max - $wallet->getLivesUsed()),
            'bonus' => $wallet->getBonusLives(),
            'resetAt' => (new DateTime('tomorrow', new DateTimeZone(self::TIMEZONE))),
        ];
    }

    public function canPlay(User $user): bool
    {
        $status = $this->status($user);

        return $status['unlimited'] || $status['left'] > 0 || $status['bonus'] > 0;
    }

    public function assert(User $user): void
    {
        if (!$this->canPlay($user)) {
            throw new NoLivesException();
        }
    }

    public function lose(User $user): void
    {
        $max = $this->allowance($user);

        if ($max === null) {
            return;
        }

        $wallet = $this->roll($this->wallets->wallet($user));

        if ($wallet->getLivesUsed() < $max) {
            $wallet->setLivesUsed($wallet->getLivesUsed() + 1);
        } elseif ($wallet->getBonusLives() > 0) {
            $wallet->setBonusLives($wallet->getBonusLives() - 1);
        }

        $this->repository->save($wallet, true);
    }

    public function add(User $user, int $amount): void
    {
        $wallet = $this->wallets->wallet($user);
        $this->repository->save($wallet->setBonusLives($wallet->getBonusLives() + max(0, $amount)), true);
    }

    public function remove(User $user, int $amount): void
    {
        $wallet = $this->wallets->wallet($user);
        $this->repository->save($wallet->setBonusLives(max(0, $wallet->getBonusLives() - max(0, $amount))), true);
    }

    protected function roll(Wallet $wallet): Wallet
    {
        $today = new DateTime('today', new DateTimeZone(self::TIMEZONE));

        if ($wallet->getLivesDay()?->format('Y-m-d') !== $today->format('Y-m-d')) {
            $wallet->setLivesDay($today)->setLivesUsed(0);
            $this->repository->save($wallet, true);
        }

        return $wallet;
    }
}
