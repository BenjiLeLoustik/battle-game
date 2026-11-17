<?php

namespace App\Shared\Shop;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use App\Shared\Shop\Exception\ShopException;
use DateTime;
use DateTimeZone;

class RewardManager
{
    public function __construct(
        protected WalletManager $wallets,
        protected WalletRepository $repository,
        protected DailyProgram $program,
    ) {
    }

    public function daily(User $user, ?DateTime $today = null): array
    {
        $today ??= $this->program->today();
        $wallet = $this->wallets->wallet($user);
        $last = $wallet->getDailyDay()?->format('Y-m-d');
        $claimed = $last === $today->format('Y-m-d');
        $continues = $claimed || $last === (clone $today)->modify('-1 day')->format('Y-m-d');
        $streak = $continues ? $wallet->getDailyStreak() : 0;
        $config = $this->program->current($today->format('Y-m-d'));
        $next = $claimed ? max(1, $streak) : $streak + 1;
        $cycle = $wallet->getChallenges()['cycle'] ?? null;

        if (!$continues || !is_array($cycle)) {
            $cycle = [
                'start' => $continues ? $next - (($next - 1) % count($config['cycle'])) : $next,
                'rewards' => $config['cycle'],
                'duplicate_coins' => $config['duplicate_coins'],
            ];
        } elseif ($next >= $cycle['start'] + count($cycle['rewards'])) {
            $cycle = ['start' => $next, 'rewards' => $config['cycle'], 'duplicate_coins' => $config['duplicate_coins']];
        }

        $index = $next - $cycle['start'];
        $rewards = array_map(fn (array $reward): array => $reward + ['label' => $this->program->describe($reward)], $cycle['rewards']);
        $tomorrow = $rewards[$index + 1] ?? $this->program->current((clone $today)->modify('+1 day')->format('Y-m-d'))['cycle'][0];
        $tomorrow['label'] ??= $this->program->describe($tomorrow);

        return [
            'claimed' => $claimed,
            'streak' => $streak,
            'day' => $index + 1,
            'reward' => $rewards[$index],
            'rewards' => $rewards,
            'tomorrow' => $tomorrow,
            'duplicate_coins' => $cycle['duplicate_coins'],
            'cycle' => $cycle,
        ];
    }

    public function challenges(User $user, ?DateTime $today = null): array
    {
        $today ??= $this->program->today();
        $config = $this->program->current($today->format('Y-m-d'));
        $claimed = $this->claimed($this->wallets->wallet($user), $today);
        $list = [];

        foreach ($config['challenges'] as $challenge) {
            $value = min($challenge['target'], $this->progress($user, $challenge, $today));
            $list[$challenge['code']] = $challenge + [
                    'label' => $this->program->challengeLabel($challenge),
                    'reward_label' => $this->program->describe($challenge['reward']),
                    'value' => $value,
                    'done' => $value >= $challenge['target'],
                    'claimed' => in_array($challenge['code'], $claimed, true),
                ];
        }

        return $list;
    }

    public function claimDaily(User $user): array
    {
        return $this->claim($user);
    }

    public function claimChallenge(User $user, string $code): array
    {
        return $this->claim($user, $code);
    }

    public function pending(User $user): int
    {
        return ($this->daily($user)['claimed'] ? 0 : 1) + count(array_filter($this->challenges($user), fn (array $challenge): bool => $challenge['done'] && !$challenge['claimed']));
    }

    protected function claim(User $user, ?string $code = null): array
    {
        if ($user->isGuest()) {
            throw new ShopException();
        }

        $orm = $this->repository->getOrm();

        return $orm->transactional(function () use ($orm, $user, $code): array {
            $orm->getConnection()->fetchOne('SELECT id FROM `user` WHERE id = :id FOR UPDATE', ['id' => $user->getId()]);
            $wallet = $this->wallets->wallet($user);
            $orm->getConnection()->fetchOne('SELECT id FROM wallet WHERE id = :id FOR UPDATE', ['id' => $wallet->getId()]);
            $orm->refresh($wallet);
            $today = $this->program->today();
            $config = $this->program->current($today->format('Y-m-d'));

            if ($code === null) {
                $status = $this->daily($user, $today);

                if ($status['claimed']) {
                    throw new ShopException();
                }

                $result = $this->program->give($user, $status['reward'], $status['duplicate_coins'], 'daily');
                $wallet->setDailyDay($today)->setDailyStreak($status['streak'] + 1)->setChallenges(array_replace($wallet->getChallenges(), ['cycle' => $status['cycle']]));
            } else {
                $challenge = $this->challenges($user, $today)[$code] ?? null;

                if ($challenge === null || !$challenge['done'] || $challenge['claimed']) {
                    throw new ShopException();
                }

                $result = $this->program->give($user, $challenge['reward'], $config['duplicate_coins'], 'challenge');
                $wallet->setChallenges(array_replace($wallet->getChallenges(), ['day' => $today->format('Y-m-d'), 'codes' => [...$this->claimed($wallet, $today), $code]]));
            }

            $this->repository->save($wallet, true);

            return $result;
        });
    }

    protected function progress(User $user, array $challenge, DateTime $today): int
    {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $params = [
            'user' => $user->getId(),
            'since' => (clone $today)->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'until' => (clone $today)->modify('+1 day')->setTimezone($timezone)->format('Y-m-d H:i:s'),
        ];
        $metric = match ($challenge['metric']) {
            'won' => 'COALESCE(SUM(r.won), 0)',
            'games' => 'COUNT(DISTINCT r.game_id)',
            default => 'COUNT(*)',
        };
        $sql = 'SELECT ' . $metric . ' FROM game_result r JOIN game_match m ON m.id = r.match_id WHERE r.user_id = :user AND r.created_at >= :since AND r.created_at < :until';

        if ($challenge['game'] !== null) {
            $sql .= ' AND r.game_id = :game';
            $params['game'] = $challenge['game'];
        }

        if ($challenge['mode'] !== 'all') {
            $sql .= ' AND m.mode = :mode';
            $params['mode'] = $challenge['mode'];
        }

        return (int) $this->repository->getOrm()->getConnection()->fetchOne($sql, $params);
    }

    protected function claimed(Wallet $wallet, DateTime $today): array
    {
        $data = $wallet->getChallenges();

        return ($data['day'] ?? null) === $today->format('Y-m-d') ? (array) ($data['codes'] ?? []) : [];
    }
}