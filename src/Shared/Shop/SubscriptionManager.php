<?php

namespace App\Shared\Shop;

use App\Entity\ShopOrder;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Entity\Game;
use App\Entity\ShopProduct;
use App\Shared\Shop\Enum\SubscriptionStatusEnum;
use DateTime;

class SubscriptionManager
{
    protected array $cache = [];

    public function __construct(
        protected SubscriptionRepository $subscriptions,
        protected SubscriptionGiftManager $gifts,
    ) {
    }

    public function current(User $user): ?Subscription
    {
        if (!array_key_exists($user->getId(), $this->cache)) {
            $current = array_values(array_filter($this->subscriptions->findBy(['user' => $user], ['endsAt' => 'DESC'], 10), fn (Subscription $subscription): bool => $subscription->isCurrent()));
            usort($current, fn (Subscription $a, Subscription $b): int => ($b->getStatus() === SubscriptionStatusEnum::ACTIVE) <=> ($a->getStatus() === SubscriptionStatusEnum::ACTIVE));
            $this->cache[$user->getId()] = $current[0] ?? null;
        }

        return $this->cache[$user->getId()];
    }

    public function active(): array
    {
        return array_values(array_filter([...$this->subscriptions->findBy(['status' => SubscriptionStatusEnum::CANCELLED]), ...$this->subscriptions->findBy(['status' => SubscriptionStatusEnum::ACTIVE])], fn (Subscription $subscription): bool => $subscription->isCurrent()));
    }

    public function offer(User $user): ?ShopProduct
    {
        return $this->current($user)?->getProduct();
    }

    public function canAccess(?User $user, Game $game): bool
    {
        if (!$game->isEarlyAccess()) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $this->offer($user)?->hasEarlyAccess() === true);
    }

    public function subscribers(ShopProduct $offer): int
    {
        return count(array_filter($this->active(), fn (Subscription $subscription): bool => $subscription->getProduct()?->getId() === $offer->getId()));
    }

    public function grant(
        User $user,
        ShopProduct $offer,
        DateTime $endsAt,
        ?string $stripeSubscription = null,
        ?string $stripeCustomer = null,
        ?array $includedGifts = null,
        ?ShopOrder $sourceOrder = null,
    ): Subscription {
        $connection = $this->subscriptions->getOrm()->getConnection();

        return $connection->transactional(function () use (
            $connection,
            $user,
            $offer,
            $endsAt,
            $stripeSubscription,
            $stripeCustomer,
            $includedGifts,
            $sourceOrder,
        ): Subscription {
            $connection->fetchOne('SELECT id FROM `user` WHERE id = :id FOR UPDATE', ['id' => $user->getId()],);

            if ($stripeSubscription === null) {
                foreach ($this->subscriptions->findBy(['user' => $user, 'stripeSubscription' => null,]) as $previous) {
                    if ($previous->isCurrent()) {
                        $this->subscriptions->save(
                            $previous
                                ->setStatus(SubscriptionStatusEnum::EXPIRED)
                                ->setEndsAt(new DateTime()),
                        );
                    }
                }
            }

            $subscription = ($stripeSubscription !== null
                ? $this->subscriptions->findOneBy(['stripeSubscription' => $stripeSubscription,])
                : null
            ) ?? (new Subscription())->setUser($user);

            $subscription
                ->setProduct($offer)
                ->setName($offer->getName())
                ->setStatus(SubscriptionStatusEnum::ACTIVE)
                ->setEndsAt($endsAt)
                ->setStripeSubscription($stripeSubscription)
                ->setStripeCustomer($stripeCustomer);

            $this->subscriptions->save($subscription, true);

            $this->gifts->issue($user, $offer, $includedGifts, $sourceOrder,);

            unset($this->cache[$user->getId()]);

            return $subscription;
        });
    }

    public function renew(string $stripeSubscription, DateTime $endsAt, bool $cancelling): void
    {
        $subscription = $this->subscriptions->findOneBy(['stripeSubscription' => $stripeSubscription]);

        if ($subscription !== null) {
            $this->subscriptions->save($subscription->setEndsAt($endsAt)->setStatus($cancelling ? SubscriptionStatusEnum::CANCELLED : SubscriptionStatusEnum::ACTIVE), true);
            unset($this->cache[$subscription->getUser()->getId()]);
        }
    }

    public function expire(string $stripeSubscription): void
    {
        $subscription = $this->subscriptions->findOneBy(['stripeSubscription' => $stripeSubscription]);

        if ($subscription !== null) {
            $this->subscriptions->save($subscription->setStatus(SubscriptionStatusEnum::EXPIRED)->setEndsAt(new DateTime()), true);
            unset($this->cache[$subscription->getUser()->getId()]);
        }
    }

    public function cancel(Subscription $subscription): void
    {
        $this->subscriptions->save($subscription->setStatus(SubscriptionStatusEnum::CANCELLED), true);
        unset($this->cache[$subscription->getUser()->getId()]);
    }
}
