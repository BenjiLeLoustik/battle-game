<?php

namespace App\Shared\Shop;

use App\Entity\ShopOrder;
use App\Entity\ShopProduct;
use App\Entity\SubscriptionGift;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserItem;
use App\Repository\BadgeRepository;
use App\Repository\ShopOrderRepository;
use App\Repository\ShopProductRepository;
use App\Repository\SubscriptionGiftRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserItemRepository;
use App\Shared\Shop\Enum\ShopOrderStatusEnum;
use App\Shared\Shop\Enum\ShopPaymentEnum;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Exception\SubscriptionGiftException;
use App\Shared\Translation\Translator;
use DateTime;

class SubscriptionGiftManager
{
    private const TYPES = ['cosmetic', 'badge', 'physical'];

    public function __construct(
        protected SubscriptionGiftRepository $gifts,
        protected ShopProductRepository $products,
        protected UserItemRepository $items,
        protected BadgeRepository $badges,
        protected UserBadgeRepository $userBadges,
        protected ShopOrderRepository $orders,
    ) {
    }

    public function candidates(): array
    {
        return array_values(array_filter(
            $this->products->findAll(['name' => 'ASC']),
            static fn (ShopProduct $product): bool => in_array(
                $product->getType()->value,
                self::TYPES,
                true,
            ),
        ));
    }

    public function normalize(array $values): array
    {
        $allowed = array_map(
            static fn (ShopProduct $product): int => $product->getId(),
            $this->candidates(),
        );

        $ids = [];

        foreach ($values as $value) {
            $id = is_scalar($value)
                ? filter_var($value, FILTER_VALIDATE_INT)
                : false;

            if ($id === false || !in_array($id, $allowed, true)) {
                throw new SubscriptionGiftException(
                    'error.shop.subscription_gifts.selection',
                );
            }

            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    public function productsFor(ShopProduct $offer): array
    {
        $selected = array_map(
            'intval',
            (array) $offer->option('included_products', []),
        );

        return array_values(array_filter(
            $this->candidates(),
            static fn (ShopProduct $product): bool => in_array(
                $product->getId(),
                $selected,
                true,
            ),
        ));
    }

    public function snapshot(ShopProduct $offer): array
    {
        return array_map(
            static fn (ShopProduct $product): array => [
                'product' => $product->getId(),
                'name' => $product->getName(),
                'type' => $product->getType()->value,
                'sizes' => $product->getSizes(),
            ],
            $this->productsFor($offer),
        );
    }

    public function issue(
        User $user,
        ShopProduct $offer,
        ?array $snapshot = null,
        ?ShopOrder $sourceOrder = null,
    ): void {
        $snapshot ??= $this->snapshot($offer);

        if ($snapshot === []) {
            return;
        }

        $connection = $this->gifts->getOrm()->getConnection();

        $connection->transactional(function () use (
            $connection,
            $user,
            $offer,
            $snapshot,
            $sourceOrder,
        ): void {
            $connection->fetchOne(
                'SELECT id FROM `user` WHERE id = :id FOR UPDATE',
                ['id' => $user->getId()],
            );

            $key = hash(
                'sha256',
                $user->getId() . ':' . $offer->getId(),
            );

            if ($this->gifts->findOneBy(['claimKey' => $key]) !== null) {
                return;
            }

            $entries = [];

            foreach ($snapshot as $item) {
                $product = $this->products->find((int) $item['product']);

                if ($product === null || $product->getType()->value !== $item['type'] || !in_array($item['type'], self::TYPES, true)) {
                    throw new SubscriptionGiftException(
                        'error.shop.subscription_gifts.unavailable',
                    );
                }

                if ($product->getType() === ShopProductTypeEnum::COSMETIC && $this->items->findOneBy(['user' => $user, 'product' => $product,]) === null) {
                    $this->items->save(
                        (new UserItem())
                            ->setUser($user)
                            ->setProduct($product),
                        true,
                    );
                }

                if ($product->getType() === ShopProductTypeEnum::BADGE) {
                    $badge = $this->badges->find(
                        (int) $product->option('badge'),
                    );

                    if ($badge === null) {
                        throw new SubscriptionGiftException(
                            'error.shop.subscription_gifts.unavailable',
                        );
                    }

                    if ($this->userBadges->findOneBy(['user' => $user, 'badge' => $badge,]) === null) {
                        $this->userBadges->save(
                            (new UserBadge())
                                ->setUser($user)
                                ->setBadge($badge),
                            true,
                        );
                    }
                }

                $entries[] = $item + [
                        'status' => $item['type'] === 'physical'
                            ? 'pending'
                            : 'granted',
                        'order' => null,
                    ];
            }

            $this->gifts->save(
                (new SubscriptionGift())
                    ->setClaimKey($key)
                    ->setUser($user)
                    ->setOffer($offer)
                    ->setOfferName($offer->getName())
                    ->setSourceOrder($sourceOrder)
                    ->setItems($entries),
                true,
            );
        });
    }

    public function forUser(User $user): array
    {
        return array_map(
            function (SubscriptionGift $gift): array {
                return [
                    'gift' => $gift,
                    'items' => array_map(
                        fn (array $item): array => $item + [
                                'delivery' => $item['order'] === null
                                    ? null
                                    : $this->orders->find(
                                        (int) $item['order'],
                                    ),
                            ],
                        $gift->getItems(),
                    ),
                ];
            },
            $this->gifts->findBy(['user' => $user], ['id' => 'DESC']),
        );
    }

    public static function address(array $data): array
    {
        $address = [];

        $limits = [
            'name' => 120,
            'line1' => 180,
            'line2' => 180,
            'postal_code' => 20,
            'city' => 100,
            'country' => 2,
            'phone' => 40,
        ];

        foreach ($limits as $field => $max) {
            $value = $data[$field] ?? '';
            $value = is_string($value) ? trim($value) : '';

            if (mb_strlen($value) > $max) {
                throw new SubscriptionGiftException(
                    'error.shop.subscription_gifts.address',
                );
            }

            $address[$field] = $value;
        }

        $address['country'] = strtoupper($address['country']);

        foreach (['name', 'line1', 'postal_code', 'city', 'country',] as $field) {
            if ($address[$field] === '') {
                throw new SubscriptionGiftException(
                    'error.shop.subscription_gifts.address',
                );
            }
        }

        if (!in_array($address['country'], ShopManager::COUNTRIES, true,)) {
            throw new SubscriptionGiftException(
                'error.shop.subscription_gifts.address',
            );
        }

        return $address;
    }

    public function claim(
        User $user,
        SubscriptionGift $gift,
        array $address,
        array $variants,
    ): void {
        $address = self::address($address);
        $connection = $this->gifts->getOrm()->getConnection();

        $connection->transactional(function () use (
            $connection,
            $user,
            $gift,
            $address,
            $variants,
        ): void {
            $connection->fetchOne(
                'SELECT id FROM `user` WHERE id = :id FOR UPDATE',
                ['id' => $user->getId()],
            );

            $connection->fetchOne(
                'SELECT id FROM subscription_gift
                 WHERE id = :id FOR UPDATE',
                ['id' => $gift->getId()],
            );

            $this->gifts->getOrm()->refresh($gift);

            if ($gift->getUser()->getId() !== $user->getId()) {
                throw new SubscriptionGiftException(
                    'error.shop.subscription_gifts.unavailable',
                );
            }

            $entries = $gift->getItems();

            usort($entries, static fn (array $a, array $b): int => $a['product'] <=> $b['product'],);

            foreach ($entries as &$item) {
                if ($item['type'] !== 'physical' || $item['status'] !== 'pending') {
                    continue;
                }

                $variant = $variants[$item['product']] ?? '';
                $variant = is_string($variant)
                    ? trim($variant)
                    : '';

                if ($item['sizes'] !== [] && !in_array($variant, $item['sizes'], true)) {
                    throw new SubscriptionGiftException(
                        'error.shop.subscription_gifts.variant',
                    );
                }

                $row = $connection->fetchAssociative(
                    'SELECT stock, type FROM shop_product
                     WHERE id = :id FOR UPDATE',
                    ['id' => $item['product']],
                );

                $product = $this->products->find(
                    (int) $item['product'],
                );

                if (!$row || $row['type'] !== 'physical' || $product === null) {
                    throw new SubscriptionGiftException(
                        'error.shop.subscription_gifts.unavailable',
                    );
                }

                if ($row['stock'] !== null) {
                    if ((int) $row['stock'] < 1) {
                        throw new SubscriptionGiftException(
                            'error.shop.subscription_gifts.stock',
                        );
                    }

                    $connection->executeStatement(
                        'UPDATE shop_product SET stock = stock - 1
                         WHERE id = :id',
                        ['id' => $item['product']],
                    );

                    $this->products->getOrm()->refresh($product);
                }

                do {
                    $reference = 'BG' . strtoupper(bin2hex(random_bytes(5)));
                } while ($this->orders->findOneBy(['reference' => $reference,]) !== null);

                $order = (new ShopOrder())
                    ->setReference($reference)
                    ->setUser($user)
                    ->setProduct($product)
                    ->setProductName($item['name'])
                    ->setType(ShopProductTypeEnum::PHYSICAL)
                    ->setPayment(ShopPaymentEnum::INCLUDED)
                    ->setAmountCents(0)
                    ->setStatus(ShopOrderStatusEnum::PAID)
                    ->setPaidAt(new DateTime())
                    ->setTermsAcceptedAt(new DateTime())
                    ->setShipping($address)
                    ->setVariant($item['sizes'] === [] ? null : $variant)
                    ->setNote(Translator::trans('site.shop.subscription_gifts.order_note', ['offer' => $gift->getOfferName()], 'messages',));

                $this->orders->save($order, true);

                $item['status'] = 'ordered';
                $item['order'] = $order->getId();
            }

            unset($item);

            $this->gifts->save(
                $gift->setItems($entries),
                true,
            );
        });
    }

    public function cancelDelivery(ShopOrder $order): void
    {
        $connection = $this->orders->getOrm()->getConnection();

        $connection->transactional(function () use (
            $connection,
            $order,
        ): void {
            $connection->fetchOne(
                'SELECT id FROM shop_order WHERE id = :id FOR UPDATE',
                ['id' => $order->getId()],
            );

            $this->orders->getOrm()->refresh($order);

            if ($order->getPayment() !== ShopPaymentEnum::INCLUDED || $order->getStatus() !== ShopOrderStatusEnum::PAID) {
                return;
            }

            if ($order->getProduct() !== null) {
                $connection->executeStatement(
                    'UPDATE shop_product SET stock = stock + 1
                     WHERE id = :id AND stock IS NOT NULL',
                    ['id' => $order->getProduct()->getId()],
                );

                $this->products->getOrm()->refresh(
                    $order->getProduct(),
                );
            }

            $this->orders->save(
                $order->setStatus(ShopOrderStatusEnum::CANCELLED),
                true,
            );
        });
    }

    public function cancelPhysical(ShopOrder $source): void
    {
        $connection = $this->gifts->getOrm()->getConnection();

        foreach ($this->gifts->findBy(['sourceOrder' => $source,]) as $gift) {
            $connection->transactional(function () use (
                $connection,
                $gift,
            ): void {
                $connection->fetchOne(
                    'SELECT id FROM `user`
                     WHERE id = :id FOR UPDATE',
                    ['id' => $gift->getUser()->getId()],
                );

                $this->gifts->getOrm()->refresh($gift);
                $entries = $gift->getItems();

                foreach ($entries as &$item) {
                    if ($item['type'] !== 'physical') {
                        continue;
                    }

                    if ($item['status'] === 'pending') {
                        $item['status'] = 'cancelled';
                    } elseif ($item['order'] !== null) {
                        $order = $this->orders->find(
                            (int) $item['order'],
                        );

                        if ($order !== null) {
                            $this->cancelDelivery($order);
                        }
                    }
                }

                unset($item);

                $this->gifts->save(
                    $gift->setItems($entries),
                    true,
                );
            });
        }
    }

    public function uses(ShopProduct $product): bool
    {
        foreach ($this->products->findBy(['type' => ShopProductTypeEnum::SUBSCRIPTION,]) as $offer) {
            if (in_array($product->getId(), array_map('intval', (array) $offer->option('included_products', []),), true,)) {
                return true;
            }
        }

        foreach ($this->gifts->findAll() as $gift) {
            if ($gift->getOffer()?->getId() === $product->getId() || in_array($product->getId(), array_map('intval', array_column($gift->getItems(), 'product'),), true,)) {
                return true;
            }
        }

        foreach ($this->orders->findBy(['type' => ShopProductTypeEnum::SUBSCRIPTION,]) as $order) {
            if (in_array($product->getId(), array_map('intval', array_column($order->getIncludedGifts() ?? [], 'product',),), true,)) {
                return true;
            }
        }

        return false;
    }
}