<?php

namespace App\Shared\Engage;

use App\Entity\ShopProduct;
use App\Entity\User;
use App\Entity\Voucher;
use App\Repository\ShopProductRepository;
use App\Repository\VoucherRepository;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Enum\VoucherKindEnum;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\SubscriptionManager;
use App\Shared\Shop\VoucherManager;
use App\Shared\Shop\WalletManager;
use App\Shared\Translation\Translator;
use DateTime;

class RewardManager
{
    public const TYPES = ['coins', 'lives', 'product', 'subscription', 'giftcard', 'custom'];

    public function __construct(
        protected ShopProductRepository $products,
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected SubscriptionManager $subscriptions,
        protected VoucherManager $vouchers,
        protected VoucherRepository $voucherRepository,
        protected SeasonPassManager $passes,
    ) {
    }

    public function normalize(array $rows): array
    {
        $rewards = [];

        foreach ($rows as $row) {
            $place = (int) ($row['place'] ?? 0);
            $type = (string) ($row['type'] ?? '');

            if ($place < 1 || $place > 500 || !in_array($type, self::TYPES, true)) {
                continue;
            }

            $reward = ['place' => $place, 'type' => $type];

            match ($type) {
                'coins', 'lives', 'giftcard' => $reward['value'] = max(0, (int) ($row['value'] ?? 0)),
                'product' => $reward['product'] = (int) ($row['product'] ?? 0),
                'subscription' => [$reward['product'], $reward['value']] = [(int) ($row['offer'] ?? 0), max(1, (int) ($row['days'] ?? 30))],
                'custom' => $reward['label'] = mb_substr(trim((string) ($row['label'] ?? '')), 0, 120),
            };

            $valid = match ($type) {
                'coins', 'lives', 'giftcard' => $reward['value'] > 0,
                'product', 'subscription' => $this->products->find($reward['product']) !== null,
                'custom' => $reward['label'] !== '',
            };

            if ($valid) {
                $rewards[] = $reward;
            }
        }

        usort($rewards, fn (array $a, array $b): int => $a['place'] <=> $b['place']);

        return $rewards;
    }

    public function describe(array $reward): string
    {
        $product = in_array($reward['type'], ['product', 'subscription'], true) ? $this->products->find((int) ($reward['product'] ?? 0)) : null;

        return match ($reward['type']) {
            'coins' => Translator::trans('site.rewards.types.coins', ['count' => $reward['value']], 'messages'),
            'lives' => Translator::trans('site.rewards.types.lives', ['count' => $reward['value']], 'messages'),
            'giftcard' => Translator::trans('site.rewards.types.giftcard', ['count' => $reward['value']], 'messages'),
            'product' => $product?->getName() ?? '—',
            'subscription' => Translator::trans('site.rewards.types.subscription', ['name' => $product?->getName() ?? '—', 'days' => $reward['value']], 'messages'),
            'custom' => (string) $reward['label'],
            default => '—',
        };
    }

    public function byPlace(array $rewards): array
    {
        $places = [];

        foreach ($rewards as $reward) {
            $places[$reward['place']][] = ['type' => $reward['type'], 'label' => $this->describe($reward)];
        }

        ksort($places);

        return $places;
    }

    public function give(User $user, array $reward, string $reason): string
    {
        $label = $this->describe($reward);

        switch ($reward['type']) {
            case 'coins':
                $this->wallets->credit($user, (int) $reward['value'], 'tournament', $reason);
                break;
            case 'lives':
                $this->lives->add($user, (int) $reward['value']);
                break;
            case 'product':
                $this->passes->grant($user, $this->products->find((int) $reward['product']));
                break;
            case 'subscription':
                $offer = $this->products->find((int) $reward['product']);

                if ($offer instanceof ShopProduct && $offer->getType() === ShopProductTypeEnum::SUBSCRIPTION) {
                    $current = $this->subscriptions->current($user);
                    $start = $current !== null && $current->isCurrent() && $current->getEndsAt() > new DateTime() ? clone $current->getEndsAt() : new DateTime();
                    $this->subscriptions->grant($user, $offer, $start->modify('+' . (int) $reward['value'] . ' days'));
                }

                break;
            case 'giftcard':
                $voucher = (new Voucher())->setCode($this->vouchers->generate('GIFT'))->setKind(VoucherKindEnum::GIFT)->setCoins((int) $reward['value'])->setMaxUses(1)->setNote(mb_substr($reason, 0, 120));
                $this->voucherRepository->save($voucher, true);
                $label .= ' · ' . $voucher->getCode();
                break;
        }

        return $label;
    }
}
