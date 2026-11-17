<?php

namespace App\Shared\Shop;

use App\Entity\ShopProduct;
use App\Entity\User;
use App\Repository\BadgeRepository;
use App\Repository\GameRepository;
use App\Repository\ShopProductRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserItemRepository;
use App\Shared\Engage\SeasonPassManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Exception\ShopException;
use App\Shared\Translation\Translator;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;

class DailyProgram
{
    public const TYPES = ['coins', 'lives', 'product'];
    public const METRICS = ['played', 'won', 'games'];
    public const MODES = ['all', 'quick', 'ranked', 'room'];

    public function __construct(
        protected SettingManager $settings,
        protected ShopProductRepository $products,
        protected GameRepository $games,
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected SeasonPassManager $passes,
        protected UserItemRepository $items,
        protected BadgeRepository $badges,
        protected UserBadgeRepository $userBadges,
    ) {
    }

    public function today(): DateTime
    {
        return new DateTime('today', new DateTimeZone('Europe/Paris'));
    }

    public function current(?string $day = null): array
    {
        $stored = $this->settings->get('daily_program');
        $day ??= $this->today()->format('Y-m-d');

        if (is_array($stored)) {
            return $stored['effective'] <= $day ? $stored['next'] : $stored['current'];
        }

        $amounts = array_values(array_filter(array_map('intval', explode(',', (string) $this->settings->get('daily_rewards'))), fn (int $amount): bool => $amount > 0));
        $challenges = [];

        foreach (['play' => ['played', 3], 'win' => ['won', 1], 'variety' => ['games', 2]] as $code => [$metric, $target]) {
            $value = (int) $this->settings->get('challenge_' . $code);

            if ($value > 0) {
                $challenges[] = ['code' => $code, 'metric' => $metric, 'target' => $target, 'game' => null, 'mode' => 'all', 'reward' => ['type' => 'coins', 'value' => $value]];
            }
        }

        return [
            'cycle' => array_map(fn (int $value): array => ['type' => 'coins', 'value' => $value], $amounts ?: [10]),
            'challenges' => $challenges,
            'duplicate_coins' => 50,
        ];
    }

    public function editable(): array
    {
        $stored = $this->settings->get('daily_program');

        return is_array($stored) ? $stored['next'] : $this->current();
    }

    public function schedule(array $config): void
    {
        $this->settings->set('daily_program', [
            'current' => $this->current(),
            'next' => $config,
            'effective' => $this->today()->modify('+1 day')->format('Y-m-d'),
        ]);
    }

    public function candidates(): array
    {
        return array_values(array_filter($this->products->findAll(['name' => 'ASC']), fn (ShopProduct $product): bool => $this->validProduct($product)));
    }

    public function normalize(array $data): array
    {
        $rows = $data['cycle'] ?? [];
        $definitions = $data['challenges'] ?? [];

        if (!is_array($rows) || count($rows) < 1 || count($rows) > 31 || !is_array($definitions) || count($definitions) > 12) {
            throw new InvalidArgumentException('admin.daily.errors.limits');
        }

        $cycle = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('admin.daily.errors.reward');
            }

            $day = $this->integer($row['day'] ?? null, 1, 31);

            if (isset($cycle[$day])) {
                throw new InvalidArgumentException('admin.daily.errors.days');
            }

            $cycle[$day] = $this->normalizeReward($row);
        }

        ksort($cycle);

        if (array_keys($cycle) !== range(1, count($cycle))) {
            throw new InvalidArgumentException('admin.daily.errors.days');
        }

        $challenges = [];
        $codes = [];

        foreach ($definitions as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('admin.daily.errors.challenge');
            }

            $code = is_string($row['code'] ?? '') ? ($row['code'] ?? '') : '!';
            $code = $code === '' ? bin2hex(random_bytes(10)) : $code;
            $metric = is_string($row['metric'] ?? null) ? $row['metric'] : '';
            $mode = is_string($row['mode'] ?? 'all') ? ($row['mode'] ?? 'all') : '';
            $game = empty($row['game']) ? null : $this->integer($row['game'], 1, PHP_INT_MAX);

            if (!preg_match('/^[a-z0-9]{1,32}$/D', $code) || isset($codes[$code]) || !in_array($metric, self::METRICS, true) || !in_array($mode, self::MODES, true)) {
                throw new InvalidArgumentException('admin.daily.errors.challenge');
            }

            if (($game !== null && $this->games->find($game) === null) || ($metric === 'games' && $game !== null)) {
                throw new InvalidArgumentException('admin.daily.errors.game');
            }

            $codes[$code] = true;
            $challenges[] = [
                'code' => $code,
                'metric' => $metric,
                'target' => $this->integer($row['target'] ?? null, 1, 1000),
                'game' => $game,
                'mode' => $mode,
                'reward' => $this->normalizeReward($row),
            ];
        }

        return [
            'cycle' => array_values($cycle),
            'challenges' => $challenges,
            'duplicate_coins' => $this->integer($data['duplicate_coins'] ?? null, 1, 100000),
        ];
    }

    public function describe(array $reward): string
    {
        return $reward['type'] === 'product'
            ? ($this->products->find((int) $reward['value'])?->getName() ?? Translator::trans('site.rewards.product_unavailable', [], 'messages'))
            : Translator::trans('site.rewards.types.' . $reward['type'], ['count' => $reward['value']], 'messages');
    }

    public function challengeLabel(array $challenge): string
    {
        $label = Translator::trans('site.rewards.objectives.' . $challenge['metric'], ['count' => $challenge['target']], 'messages');

        if ($challenge['game'] !== null) {
            $game = $this->games->find($challenge['game']);
            $label .= ' · ' . ($game?->getName() ?? Translator::trans('site.rewards.game_unavailable', [], 'messages'));
        }

        if ($challenge['mode'] !== 'all') {
            $label .= ' · ' . Translator::trans('game_match_mode.' . $challenge['mode'], [], 'enums');
        }

        return $label;
    }

    public function give(User $user, array $reward, int $duplicateCoins, string $reason): array
    {
        $coins = 0;
        $label = $this->describe($reward);

        if ($reward['type'] === 'coins') {
            $coins = (int) $reward['value'];
        } elseif ($reward['type'] === 'lives') {
            $this->lives->add($user, (int) $reward['value']);
        } else {
            $product = $this->products->find((int) $reward['value']);

            if (!$product instanceof ShopProduct || !$this->validProduct($product)) {
                throw new ShopException();
            }

            $owned = $product->getType()->value === 'cosmetic'
                ? $this->items->findOneBy(['user' => $user, 'product' => $product]) !== null
                : isset($this->userBadges->obtained($user)[(int) $product->option('badge')]);

            if ($owned) {
                $coins = $duplicateCoins;
                $label = $this->describe(['type' => 'coins', 'value' => $coins]);
            } else {
                $this->passes->grant($user, $product);
            }
        }

        $this->wallets->credit($user, $coins, $reason, $label);

        return ['coins' => $coins, 'label' => $label];
    }

    protected function normalizeReward(array $row): array
    {
        $type = is_string($row['type'] ?? null) ? $row['type'] : '';

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('admin.daily.errors.reward');
        }

        $value = $this->integer($row[$type === 'product' ? 'product' : 'value'] ?? null, 1, $type === 'product' ? PHP_INT_MAX : 100000);

        if ($type === 'product' && !$this->validProduct($this->products->find($value))) {
            throw new InvalidArgumentException('admin.daily.errors.reward');
        }

        return ['type' => $type, 'value' => $value];
    }

    protected function validProduct(?ShopProduct $product): bool
    {
        return $product !== null && ($product->getType()->value === 'cosmetic' || ($product->getType()->value === 'badge' && $this->badges->find((int) $product->option('badge')) !== null));
    }

    protected function integer(mixed $value, int $min, int $max): int
    {
        $number = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if ($number === false || $number < $min || $number > $max) {
            throw new InvalidArgumentException('admin.daily.errors.number');
        }

        return $number;
    }
}