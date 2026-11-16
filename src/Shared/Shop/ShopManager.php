<?php

namespace App\Shared\Shop;

use App\Entity\ShopOrder;
use App\Entity\ShopProduct;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserItem;
use App\Repository\BadgeRepository;
use App\Repository\ShopOrderRepository;
use App\Repository\ShopProductRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserItemRepository;
use App\Shared\Shop\Enum\CosmeticKindEnum;
use App\Shared\Shop\Enum\ShopOrderStatusEnum;
use App\Shared\Shop\Enum\ShopPaymentEnum;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Enum\SubscriptionStatusEnum;
use App\Shared\Shop\Exception\PaymentException;
use App\Shared\Shop\Exception\ShopException;
use App\Shared\Shop\Exception\SubscribedException;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;

class ShopManager
{
    public const COUNTRIES = ['FR', 'BE', 'CH', 'LU', 'MC', 'CA'];

    protected array $cosmetics = [];

    public function __construct(
        protected ShopProductRepository $products,
        protected ShopOrderRepository $orders,
        protected UserItemRepository $items,
        protected BadgeRepository $badges,
        protected UserBadgeRepository $userBadges,
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected SubscriptionManager $subscriptions,
        protected StripeClient $stripe,
        protected InvoiceManager $invoices,
        protected NotificationManager $notifications,
        protected VoucherManager $vouchers,
        protected SubscriptionGiftManager $gifts,
    ) {
    }

    public function catalog(?ShopProductTypeEnum $type = null): array
    {
        $criteria = ['active' => true];

        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return $this->products->findBy($criteria, ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function owns(User $user, ShopProduct $product): bool
    {
        return match ($product->getType()) {
            ShopProductTypeEnum::COSMETIC => $this->items->findOneBy(['user' => $user, 'product' => $product]) !== null,
            ShopProductTypeEnum::BADGE => ($badge = $this->badges->find((int) $product->option('badge'))) !== null && isset($this->userBadges->obtained($user)[$badge->getId()]),
            ShopProductTypeEnum::SUBSCRIPTION => $this->subscriptions->offer($user)?->getId() === $product->getId(),
            default => false,
        };
    }

    public function buyWithCoins(User $user, ShopProduct $product): ShopOrder
    {
        $this->check($user, $product);

        if (!$product->getType()->allowsCoins() || $product->getPriceCoins() === null) {
            throw new ShopException();
        }

        $order = $this->order($user, $product, ShopPaymentEnum::COINS);
        $this->wallets->debit($user, $product->getPriceCoins(), 'shop', $product->getName());
        $order->setStatus(ShopOrderStatusEnum::PAID)->setPaidAt(new DateTime());
        $this->orders->save($order, true);
        $this->fulfill($order);

        return $order;
    }

    public function checkout(User $user, ShopProduct $product, string $successUrl, string $cancelUrl, ?string $variant = null, ?string $code = null): string
    {
        $this->check($user, $product);
        $sizes = $product->getSizes();

        if ($product->getPriceCents() === null || ($product->getType() === ShopProductTypeEnum::PHYSICAL && $sizes !== [] && !in_array($variant, $sizes, true))) {
            throw new ShopException();
        }

        $voucher = $code !== null && trim($code) !== '' ? $this->vouchers->promo($user, $product, $code) : null;
        $discount = $voucher?->discount((int) $product->getPriceCents()) ?? 0;
        $order = $this->order($user, $product, ShopPaymentEnum::CARD)->setVariant($sizes !== [] ? $variant : null)->setTermsAcceptedAt(new DateTime());
        $order->setVoucherCode($voucher?->getCode())->setDiscountCents($discount > 0 ? $discount : null)->setAmountCents((int) $product->getPriceCents() - $discount);
        $this->orders->save($order, true);
        $subscription = $product->getType() === ShopProductTypeEnum::SUBSCRIPTION;

        if ($subscription) {
            $this->orders->save(
                $order->setIncludedGifts($this->gifts->snapshot($product)),
                true
            );
        }

        $data = [
            'mode' => $subscription ? 'subscription' : 'payment',
            'client_reference_id' => $order->getReference(),
            'metadata' => ['order' => $order->getReference()],
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'locale' => 'fr',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => array_filter([
                    'currency' => 'eur',
                    'unit_amount' => $order->getAmountCents(),
                    'product_data' => ['name' => $product->getName() . ($order->getVariant() ? ' - ' . $order->getVariant() : '')],
                    'recurring' => $subscription ? ['interval' => 'month'] : null,
                ]),
            ]],
        ];

        if ($subscription) {
            $data['subscription_data'] = ['metadata' => ['order' => $order->getReference()]];
        }

        if ($product->getType() === ShopProductTypeEnum::PHYSICAL) {
            $data['shipping_address_collection'] = ['allowed_countries' => self::COUNTRIES];
            $data['phone_number_collection'] = ['enabled' => 'true'];
        }

        $session = $this->stripe->call('POST', 'checkout/sessions', $data);
        $this->orders->save($order->setStripeSession((string) ($session['id'] ?? '')), true);

        return (string) ($session['url'] ?? $cancelUrl);
    }

    public function confirm(User $user, string $sessionId): ?ShopOrder
    {
        $order = $this->orders->findOneBy(['stripeSession' => $sessionId]);

        if ($order === null || $order->getUser()?->getId() !== $user->getId()) {
            return null;
        }

        if ($order->getStatus() === ShopOrderStatusEnum::PENDING) {
            $session = $this->stripe->call('GET', 'checkout/sessions/' . rawurlencode($sessionId));

            if (($session['status'] ?? '') === 'complete' && in_array($session['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
                $this->complete($order, $session);
            }
        }

        return $order;
    }

    public function webhook(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $order = $this->orders->findOneBy(['reference' => (string) ($object['client_reference_id'] ?? $object['metadata']['order'] ?? '')]);

                if ($order !== null && in_array($object['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
                    $this->complete($order, $object);
                }
                break;
            case 'invoice.paid':
                $id = $object['subscription'] ?? $object['parent']['subscription_details']['subscription'] ?? null;
                $end = $object['lines']['data'][0]['period']['end'] ?? null;

                if (is_string($id) && $end !== null) {
                    $this->subscriptions->renew($id, (new DateTime())->setTimestamp((int) $end), false);
                }
                break;
            case 'customer.subscription.updated':
                $end = $object['current_period_end'] ?? $object['items']['data'][0]['current_period_end'] ?? null;

                if (isset($object['id']) && $end !== null) {
                    $this->subscriptions->renew((string) $object['id'], (new DateTime())->setTimestamp((int) $end), (bool) ($object['cancel_at_period_end'] ?? false));
                }
                break;
            case 'customer.subscription.deleted':
                if (isset($object['id'])) {
                    $this->subscriptions->expire((string) $object['id']);
                }
                break;
        }
    }

    public function completeUnlocked(ShopOrder $order, array $session = []): void
    {
        if ($order->getStatus() !== ShopOrderStatusEnum::PENDING) {
            return;
        }

        $shipping = $session['collected_information']['shipping_details'] ?? $session['shipping_details'] ?? null;

        if (is_array($shipping)) {
            $order->setShipping(array_filter([
                'name' => $shipping['name'] ?? null,
                'line1' => $shipping['address']['line1'] ?? null,
                'line2' => $shipping['address']['line2'] ?? null,
                'postal_code' => $shipping['address']['postal_code'] ?? null,
                'city' => $shipping['address']['city'] ?? null,
                'country' => $shipping['address']['country'] ?? null,
                'phone' => $session['customer_details']['phone'] ?? null,
            ]));
        }

        if (isset($session['amount_total'])) {
            $order->setAmountCents((int) $session['amount_total']);
        }

        $order->setStatus(ShopOrderStatusEnum::PAID)->setPaidAt(new DateTime());
        $this->orders->save($order, true);
        $this->fulfill($order, is_string($session['subscription'] ?? null) ? $session['subscription'] : null, is_string($session['customer'] ?? null) ? $session['customer'] : null);
        $this->vouchers->consume($order);
        $this->invoices->issue($order);
    }

    public function complete(ShopOrder $order, array $session = []): void
    {
        $connection = $this->orders->getOrm()->getConnection();
        $completed = $connection->transactional(function () use ($connection, $order, $session): bool {
            $connection->fetchOne('SELECT id FROM shop_order WHERE id = :id FOR UPDATE', ['id' => $order->getId()]);

            $this->orders->getOrm()->refresh($order);

            if ($order->getStatus() !== ShopOrderStatusEnum::PENDING) {
                return false;
            }

            $this->completeUnlocked($order, $session);

            return true;
        });

        if ($completed) {
            $this->notify($order, NotificationTypeEnum::SHOP_PAID);
        }
    }

    public function fulfill(ShopOrder $order, ?string $stripeSubscription = null, ?string $stripeCustomer = null): void
    {
        $user = $order->getUser();
        $product = $order->getProduct();

        if ($user === null || $product === null) {
            return;
        }

        if ($product->getStock() !== null) {
            $this->products->getOrm()->getConnection()->executeStatement('UPDATE shop_product SET stock = GREATEST(stock - 1, 0) WHERE id = :id', ['id' => $product->getId()]);
            $product->setStock(max(0, $product->getStock() - 1));
        }

        switch ($product->getType()) {
            case ShopProductTypeEnum::COINS:
                $this->wallets->credit($user, (int) $product->option('coins', 0), 'purchase', $product->getName());
                break;
            case ShopProductTypeEnum::LIVES:
                $this->lives->add($user, (int) $product->option('lives', 0));
                break;
            case ShopProductTypeEnum::BADGE:
                $badge = $this->badges->find((int) $product->option('badge'));

                if ($badge !== null && !isset($this->userBadges->obtained($user)[$badge->getId()])) {
                    $this->userBadges->save((new UserBadge())->setUser($user)->setBadge($badge), true);
                }
                break;
            case ShopProductTypeEnum::COSMETIC:
                if ($this->items->findOneBy(['user' => $user, 'product' => $product]) === null) {
                    $this->items->save((new UserItem())->setUser($user)->setProduct($product), true);
                }
                break;
            case ShopProductTypeEnum::SUBSCRIPTION:
                $endsAt = $this->periodEnd($stripeSubscription) ?? new DateTime('+1 month');
                $this->subscriptions->grant($user, $product, $endsAt, $stripeSubscription, $stripeCustomer, $order->getIncludedGifts(), $order,);
                break;
            case ShopProductTypeEnum::PHYSICAL:
                break;
            case ShopProductTypeEnum::GIFT:
                $this->vouchers->issueGift($order, (int) $product->option('coins', 0));
                break;
        }
    }

    public function ship(ShopOrder $order, ?string $tracking): void
    {
        $connection = $this->orders->getOrm()->getConnection();

        $connection->transactional(function ()  use ($connection, $order, $tracking): void {
            $connection->fetchOne('SELECT id FROM shop_order WHERE id = :id FOR UPDATE', ['id' => $order->getId()]);

            $this->orders->getOrm()->refresh($order);

            if ($order->getType() !== ShopProductTypeEnum::PHYSICAL || $order->getStatus() !== ShopOrderStatusEnum::PAID || $order->getShipping() === []) {
                throw new ShopException();
            }

            $this->orders->save(
                $order->setStatus(ShopOrderStatusEnum::SHIPPED)
                    ->setTracking($tracking !== '' ? $tracking : null),
                true
            );
        });

        $this->notify($order, NotificationTypeEnum::SHOP_SHIPPED);
    }

    public function cancel(ShopOrder $order): void
    {
        if ($order->getPayment() === ShopPaymentEnum::INCLUDED) {
            $this->gifts->cancelDelivery($order);

            return;
        }

        if (in_array($order->getStatus(), [ShopOrderStatusEnum::PENDING, ShopOrderStatusEnum::PAID], true)) {
            if ($order->getType() === ShopProductTypeEnum::SUBSCRIPTION) {
                $this->gifts->cancelPhysical($order);
            }

            $this->orders->save(
                $order->setStatus(ShopOrderStatusEnum::CANCELLED),
                true,
            );
        }
    }

    public function refund(ShopOrder $order): void
    {
        if ($order->getPayment() === ShopPaymentEnum::INCLUDED) {
            throw new ShopException();
        }

        if (!in_array($order->getStatus(), [ShopOrderStatusEnum::PAID, ShopOrderStatusEnum::SHIPPED], true)) {
            throw new ShopException();
        }

        $user = $order->getUser();

        if ($order->getPayment() === ShopPaymentEnum::COINS) {
            if ($user !== null) {
                $this->wallets->credit($user, (int) $order->getAmountCoins(), 'refund', $order->getProductName());
            }
        } elseif ($order->getStripeSession() !== null) {
            $session = $this->stripe->call('GET', 'checkout/sessions/' . rawurlencode($order->getStripeSession()));
            $subscription = is_string($session['subscription'] ?? null) ? $session['subscription'] : null;

            if ($subscription !== null) {
                $this->stopSubscription($subscription);
            }

            $this->stripe->call('POST', 'refunds', $this->payment($session));

            if ($subscription !== null) {
                $this->subscriptions->expire($subscription);
            }
        }

        if ($user !== null) {
            $this->revoke($order, $user);
        }

        $this->orders->save($order->setStatus(ShopOrderStatusEnum::REFUNDED), true);
        $this->invoices->credit($order);
        $this->notify($order, NotificationTypeEnum::SHOP_REFUNDED);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        if ($subscription->getStripeSubscription() !== null) {
            $this->stripe->call('POST', 'subscriptions/' . rawurlencode($subscription->getStripeSubscription()), ['cancel_at_period_end' => 'true']);
        }

        $this->subscriptions->cancel($subscription);
    }

    public function inventory(User $user): array
    {
        return $this->items->findBy(['user' => $user], ['id' => 'DESC']);
    }

    public function equip(User $user, UserItem $item): void
    {
        if ($item->getUser()->getId() !== $user->getId()) {
            throw new ShopException();
        }

        $equip = !$item->isEquipped();
        $kind = $item->getProduct()->option('kind');

        foreach ($this->inventory($user) as $other) {
            if ($other->isEquipped() && $other->getProduct()->option('kind') === $kind) {
                $this->items->save($other->setEquipped(false));
            }
        }

        $this->items->save($item->setEquipped($equip), true);
        $this->cosmetics = [];
    }

    public function cosmetics(int $user): array
    {
        if ($this->cosmetics === []) {
            $this->cosmetics = ['*' => []];

            foreach ($this->items->findBy(['equipped' => true]) as $item) {
                $kind = CosmeticKindEnum::tryFrom((string) $item->getProduct()->option('kind'));

                if ($kind !== null && $item->getProduct()->option('value') !== null) {
                    $this->cosmetics[$item->getUser()->getId()] = array_merge($this->cosmetics[$item->getUser()->getId()] ?? [], CosmeticDesign::look($item->getProduct()));
                }
            }

            foreach ($this->subscriptions->active() as $subscription) {
                $this->cosmetics[$subscription->getUser()->getId()]['plan'] = $subscription->getBadge();
            }

            foreach ($this->products->getOrm()->getConnection()->fetchAllAssociative('SELECT m.user_id, c.tag, c.color FROM clan_member m JOIN clan c ON c.id = m.clan_id') as $row) {
                $this->cosmetics[(int) $row['user_id']]['clan'] = ['tag' => $row['tag'], 'color' => $row['color']];
            }
        }

        return $this->cosmetics[$user] ?? [];
    }

    protected function check(User $user, ShopProduct $product): void
    {
        if (!$product->isAvailable() || $this->owns($user, $product)) {
            throw new ShopException();
        }

        $current = $this->subscriptions->current($user);

        if ($product->getType() === ShopProductTypeEnum::SUBSCRIPTION && $current !== null && $current->getStatus() === SubscriptionStatusEnum::ACTIVE) {
            throw new SubscribedException();
        }
    }

    protected function order(User $user, ShopProduct $product, ShopPaymentEnum $payment): ShopOrder
    {
        $order = (new ShopOrder())
            ->setReference('BG' . strtoupper(bin2hex(random_bytes(5))))
            ->setUser($user)
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setType($product->getType())
            ->setPayment($payment)
            ->setAmountCents($payment === ShopPaymentEnum::CARD ? $product->getPriceCents() : null)
            ->setAmountCoins($payment === ShopPaymentEnum::COINS ? $product->getPriceCoins() : null);
        $this->orders->save($order, true);

        return $order;
    }

    protected function notify(ShopOrder $order, NotificationTypeEnum $type): void
    {
        if ($order->getUser() === null) {
            return;
        }

        $this->notifications->notify($order->getUser(), $type, [
            'product' => $order->getProductName() . ($order->getVariant() ? ' (' . $order->getVariant() . ')' : ''),
            'reference' => $order->getReference(),
            'tracking' => $order->getTracking() ?? '—',
        ]);
    }

    protected function revoke(ShopOrder $order, User $user): void
    {
        $product = $order->getProduct();

        if ($product === null) {
            return;
        }

        switch ($order->getType()) {
            case ShopProductTypeEnum::COINS:
                $coins = min((int) $product->option('coins', 0), $this->wallets->balance($user));

                if ($coins > 0) {
                    $this->wallets->debit($user, $coins, 'refund', $order->getProductName());
                }
                break;
            case ShopProductTypeEnum::LIVES:
                $this->lives->remove($user, (int) $product->option('lives', 0));
                break;
            case ShopProductTypeEnum::BADGE:
                $badge = $this->badges->find((int) $product->option('badge'));
                $owned = $badge === null ? null : $this->userBadges->findOneBy(['user' => $user, 'badge' => $badge]);

                if ($owned !== null) {
                    $this->userBadges->delete($owned, true);
                }
                break;
            case ShopProductTypeEnum::COSMETIC:
                $item = $this->items->findOneBy(['user' => $user, 'product' => $product]);

                if ($item !== null) {
                    $this->items->delete($item, true);
                    $this->cosmetics = [];
                }
                break;
            case ShopProductTypeEnum::PHYSICAL:
                if ($order->getStatus() === ShopOrderStatusEnum::PAID && $product->getStock() !== null) {
                    $this->products->save($product->setStock($product->getStock() + 1), true);
                }
                break;
            case ShopProductTypeEnum::SUBSCRIPTION:
                $this->gifts->cancelPhysical($order);
                $this->cosmetics = [];
                break;
            case ShopProductTypeEnum::GIFT:
                $this->vouchers->revokeGift($order);
                break;
        }
    }

    protected function stopSubscription(string $id): void
    {
        try {
            $this->stripe->call('DELETE', 'subscriptions/' . rawurlencode($id));
        } catch (PaymentException $exception) {
            $current = $this->stripe->call('GET', 'subscriptions/' . rawurlencode($id));

            if (($current['status'] ?? '') !== 'canceled') {
                throw $exception;
            }
        }
    }

    protected function payment(array $session): array
    {
        if (is_string($session['payment_intent'] ?? null)) {
            return ['payment_intent' => $session['payment_intent']];
        }

        $invoice = is_string($session['invoice'] ?? null) ? $session['invoice'] : null;

        if ($invoice !== null) {
            $data = $this->stripe->call('GET', 'invoices/' . rawurlencode($invoice) . '?expand[]=payments');

            if (is_string($data['payment_intent'] ?? null)) {
                return ['payment_intent' => $data['payment_intent']];
            }

            foreach ($data['payments']['data'] ?? [] as $payment) {
                if (is_string($payment['payment']['payment_intent'] ?? null)) {
                    return ['payment_intent' => $payment['payment']['payment_intent']];
                }

                if (is_string($payment['payment']['charge'] ?? null)) {
                    return ['charge' => $payment['payment']['charge']];
                }
            }

            $list = $this->stripe->call('GET', 'invoice_payments?invoice=' . rawurlencode($invoice));

            foreach ($list['data'] ?? [] as $payment) {
                if (is_string($payment['payment']['payment_intent'] ?? null)) {
                    return ['payment_intent' => $payment['payment']['payment_intent']];
                }
            }

            if (is_string($data['charge'] ?? null)) {
                return ['charge' => $data['charge']];
            }
        }

        throw new ShopException();
    }

    protected function periodEnd(?string $stripeSubscription): ?DateTime
    {
        if ($stripeSubscription === null) {
            return null;
        }

        $subscription = $this->stripe->call('GET', 'subscriptions/' . rawurlencode($stripeSubscription));
        $end = $subscription['current_period_end'] ?? $subscription['items']['data'][0]['current_period_end'] ?? null;

        return $end === null ? null : (new DateTime())->setTimestamp((int) $end);
    }
}
