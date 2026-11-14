<?php

namespace App\Shared\Shop;

use App\Entity\ShopOrder;
use App\Entity\ShopProduct;
use App\Entity\User;
use App\Entity\Voucher;
use App\Entity\VoucherUse;
use App\Repository\VoucherRepository;
use App\Repository\VoucherUseRepository;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Enum\VoucherKindEnum;
use App\Shared\Shop\Exception\VoucherException;

class VoucherManager
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        protected VoucherRepository $vouchers,
        protected VoucherUseRepository $uses,
        protected WalletManager $wallets,
    ) {
    }

    public function generate(string $prefix = ''): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 12; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $code = ($prefix !== '' ? strtoupper($prefix) . '-' : '') . implode('-', str_split($code, 4));
        } while ($this->vouchers->findOneBy(['code' => $code]) !== null);

        return $code;
    }

    public function normalize(string $code): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', '', $code)));
    }

    public function promo(User $user, ShopProduct $product, string $code): Voucher
    {
        $voucher = $this->vouchers->findOneBy(['code' => $this->normalize($code), 'kind' => VoucherKindEnum::PROMO]);

        if ($voucher === null || !$voucher->isUsable() || $product->getType() === ShopProductTypeEnum::SUBSCRIPTION || $this->used($voucher, $user) || $voucher->discount((int) $product->getPriceCents()) <= 0) {
            throw new VoucherException();
        }

        return $voucher;
    }

    public function consume(ShopOrder $order): void
    {
        if ($order->getVoucherCode() === null || $order->getUser() === null) {
            return;
        }

        $voucher = $this->vouchers->findOneBy(['code' => $order->getVoucherCode()]);

        if ($voucher !== null && $this->uses->findOneBy(['voucher' => $voucher, 'order' => $order]) === null) {
            $this->vouchers->save($voucher->setUses($voucher->getUses() + 1));
            $this->uses->save((new VoucherUse())->setVoucher($voucher)->setUser($order->getUser())->setOrder($order), true);
        }
    }

    public function redeem(User $user, string $code): Voucher
    {
        $voucher = $this->vouchers->findOneBy(['code' => $this->normalize($code), 'kind' => VoucherKindEnum::GIFT]);

        if ($voucher === null || !$voucher->isUsable() || $this->used($voucher, $user) || (int) $voucher->getCoins() <= 0) {
            throw new VoucherException();
        }

        $changed = $this->vouchers->getOrm()->getConnection()->executeStatement(
            'UPDATE voucher SET uses = uses + 1 WHERE id = :id AND active = 1 AND (max_uses IS NULL OR uses < max_uses)',
            ['id' => $voucher->getId()],
        );

        if ($changed === 0) {
            throw new VoucherException();
        }

        $voucher->setUses($voucher->getUses() + 1);
        $this->uses->save((new VoucherUse())->setVoucher($voucher)->setUser($user), true);
        $this->wallets->credit($user, (int) $voucher->getCoins(), 'gift', $voucher->getCode());

        return $voucher;
    }

    public function giftFor(ShopOrder $order): ?Voucher
    {
        return $this->vouchers->findOneBy(['order' => $order, 'kind' => VoucherKindEnum::GIFT]);
    }

    public function issueGift(ShopOrder $order, int $coins): Voucher
    {
        $voucher = $this->giftFor($order) ?? (new Voucher())
            ->setCode($this->generate('GIFT'))
            ->setKind(VoucherKindEnum::GIFT)
            ->setCoins($coins)
            ->setMaxUses(1)
            ->setOrder($order)
            ->setNote($order->getReference());
        $this->vouchers->save($voucher, true);

        return $voucher;
    }

    public function revokeGift(ShopOrder $order): void
    {
        $voucher = $this->giftFor($order);

        if ($voucher !== null) {
            $this->vouchers->save($voucher->setActive(false), true);
        }
    }

    protected function used(Voucher $voucher, User $user): bool
    {
        return $this->uses->findOneBy(['voucher' => $voucher, 'user' => $user]) !== null;
    }
}
