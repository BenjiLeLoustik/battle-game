<?php

namespace App\Shared\Shop;

use App\Entity\CoinTransaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\CoinTransactionRepository;
use App\Repository\WalletRepository;
use App\Shared\Shop\Exception\NotEnoughCoinsException;

class WalletManager
{
    public function __construct(
        protected WalletRepository $wallets,
        protected CoinTransactionRepository $transactions,
    ) {
    }

    public function wallet(User $user): Wallet
    {
        $wallet = $this->wallets->findOneBy(['user' => $user]);

        if ($wallet === null) {
            $wallet = (new Wallet())->setUser($user);
            $this->wallets->save($wallet, true);
        }

        return $wallet;
    }

    public function balance(User $user): int
    {
        return $this->wallet($user)->getCoins();
    }

    public function credit(User $user, int $amount, string $reason, string $label): void
    {
        if ($amount === 0) {
            return;
        }

        $wallet = $this->wallet($user);
        $changed = $this->wallets->getOrm()->getConnection()->executeStatement(
            'UPDATE wallet SET coins = coins + :amount WHERE id = :id AND coins + :amount >= 0',
            ['amount' => $amount, 'id' => $wallet->getId()],
        );

        if ($changed === 0) {
            throw new NotEnoughCoinsException();
        }

        $wallet->setCoins($wallet->getCoins() + $amount);
        $this->transactions->save((new CoinTransaction())->setUser($user)->setAmount($amount)->setReason(mb_substr($reason, 0, 40))->setLabel(mb_substr($label, 0, 160)), true);
    }

    public function debit(User $user, int $amount, string $reason, string $label): void
    {
        $this->credit($user, -abs($amount), $reason, $label);
    }

    public function history(User $user, int $limit = 20): array
    {
        return $this->transactions->findBy(['user' => $user], ['id' => 'DESC'], $limit);
    }
}
