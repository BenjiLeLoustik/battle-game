<?php

namespace App\Shared\Engage;

use App\Entity\Season;
use App\Entity\SeasonPass;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserItem;
use App\Repository\BadgeRepository;
use App\Repository\SeasonPassRepository;
use App\Repository\SeasonRepository;
use App\Repository\ShopProductRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserItemRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Shop\Exception\ShopException;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\SubscriptionManager;
use App\Shared\Shop\WalletManager;

class SeasonPassManager
{
    public const FREE = 'free';
    public const TYPES = ['coins', 'lives', 'product'];

    public function __construct(
        protected SeasonPassRepository $passes,
        protected SeasonRepository $seasons,
        protected SettingManager $settings,
        protected SubscriptionManager $subscriptions,
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected ShopProductRepository $products,
        protected UserItemRepository $items,
        protected BadgeRepository $badges,
        protected UserBadgeRepository $userBadges,
    ) {
    }

    public function tiers(?Season $season = null): array
    {
        $season ??= $this->seasons->current();

        return $season?->getPass()?->getTiers() ?? [];
    }

    public function tracks(): array
    {
        $tracks = [];

        foreach ((array) $this->settings->get('pass_tracks') as $track) {
            $key = is_array($track) ? (string) ($track['key'] ?? '') : '';

            if (preg_match('/^[a-z0-9_]{1,20}$/', $key) && $key !== self::FREE && $key !== 'xp') {
                $tracks[$key] = [
                    'key' => $key,
                    'name' => mb_substr(trim((string) ($track['name'] ?? '')), 0, 40) ?: $key,
                    'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($track['color'] ?? '')) ? strtoupper($track['color']) : '#FFC94A',
                ];
            }
        }

        return array_values($tracks);
    }

    public function keys(): array
    {
        return [self::FREE, ...array_column($this->tracks(), 'key')];
    }

    public function owned(User $user): array
    {
        $offer = $this->subscriptions->offer($user);
        $keys = array_column($this->tracks(), 'key');
        $chosen = $offer === null ? [] : (array) $offer->option('pass_tracks', []);

        if ($offer !== null && $chosen === [] && $offer->option('season_pass') === true && in_array('premium', $keys, true)) {
            $chosen = ['premium'];
        }

        return [self::FREE, ...array_values(array_intersect($keys, $chosen))];
    }

    public function pass(User $user, ?Season $season = null): ?SeasonPass
    {
        $season ??= $this->seasons->current();

        if ($season === null || $user->isGuest()) {
            return null;
        }

        $pass = $this->passes->findOneBy(['user' => $user, 'season' => $season]);

        if ($pass === null) {
            $pass = (new SeasonPass())->setUser($user)->setSeason($season);
            $this->passes->save($pass, true);
        }

        return $pass;
    }

    public function add(User $user, int $xp): void
    {
        $pass = $xp > 0 ? $this->pass($user) : null;

        if ($pass !== null) {
            $this->passes->save($pass->setXp($pass->getXp() + $xp), true);
        }
    }

    public function premium(User $user): bool
    {
        return count($this->owned($user)) > 1;
    }

    public function status(User $user): ?array
    {
        $pass = $this->pass($user);

        if ($pass === null) {
            return null;
        }

        $owned = $this->owned($user);
        $keys = $this->keys();
        $claimed = $pass->getClaimed();
        $tiers = [];
        $level = 0;

        foreach ($this->tiers($pass->getSeason()) as $index => $tier) {
            $number = $index + 1;
            $unlocked = $pass->getXp() >= (int) $tier['xp'];
            $level = $unlocked ? $number : $level;
            $rewards = [];

            foreach ($keys as $track) {
                $reward = $tier[$track] ?? null;

                if (!is_array($reward) || !in_array($reward['type'] ?? '', self::TYPES, true)) {
                    $rewards[$track] = null;
                    continue;
                }

                $product = ($reward['type'] === 'product') ? $this->products->find((int) ($reward['value'] ?? 0)) : null;
                $rewards[$track] = [
                    'type' => $reward['type'],
                    'value' => (int) ($reward['value'] ?? 0),
                    'product' => $product,
                    'claimed' => in_array($number, $claimed[$track] ?? [], true),
                    'owned' => in_array($track, $owned, true),
                    'available' => $unlocked && in_array($track, $owned, true) && !in_array($number, $claimed[$track] ?? [], true),
                ];
            }

            $tiers[] = ['number' => $number, 'xp' => (int) $tier['xp'], 'unlocked' => $unlocked, 'rewards' => $rewards];
        }

        $next = current(array_filter($tiers, fn (array $tier): bool => !$tier['unlocked'])) ?: null;
        $previous = $level > 0 ? $tiers[$level - 1]['xp'] : 0;

        return [
            'season' => $pass->getSeason(),
            'xp' => $pass->getXp(),
            'level' => $level,
            'premium' => count($owned) > 1,
            'tracks' => array_map(fn (array $track): array => $track + ['owned' => in_array($track['key'], $owned, true)], $this->tracks()),
            'locked' => count($owned) < count($keys),
            'tiers' => $tiers,
            'next' => $next,
            'progress' => $next === null ? 100 : (int) round(($pass->getXp() - $previous) / max(1, $next['xp'] - $previous) * 100),
            'pending' => count(array_filter(array_merge(...array_map(fn (array $tier): array => array_values(array_filter($tier['rewards'])), $tiers ?: [['rewards' => []]])), fn (array $reward): bool => $reward['available'])),
        ];
    }

    public function claim(User $user, int $number, string $track): array
    {
        $status = $this->status($user);
        $reward = $status['tiers'][$number - 1]['rewards'][$track] ?? null;

        if ($status === null || $reward === null || !$reward['available']) {
            throw new ShopException();
        }

        $pass = $this->pass($user);
        $claimed = $pass->getClaimed();
        $claimed[$track] = [...($claimed[$track] ?? []), $number];
        $this->passes->save($pass->setClaimed($claimed), true);
        $label = 'Pass · palier ' . $number;

        match ($reward['type']) {
            'coins' => $this->wallets->credit($user, $reward['value'], 'pass', $label),
            'lives' => $this->lives->add($user, $reward['value']),
            'product' => $this->grant($user, $reward['product']),
        };

        return $reward;
    }

    public function grant(User $user, $product): void
    {
        if ($product === null) {
            return;
        }

        if ($product->getType() === ShopProductTypeEnum::COSMETIC && $this->items->findOneBy(['user' => $user, 'product' => $product]) === null) {
            $this->items->save((new UserItem())->setUser($user)->setProduct($product), true);
        } elseif ($product->getType() === ShopProductTypeEnum::BADGE) {
            $badge = $this->badges->find((int) $product->option('badge'));

            if ($badge !== null && !isset($this->userBadges->obtained($user)[$badge->getId()])) {
                $this->userBadges->save((new UserBadge())->setUser($user)->setBadge($badge), true);
            }
        }
    }
}
