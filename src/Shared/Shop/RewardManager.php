<?php

namespace App\Shared\Shop;

use App\Entity\User;
use App\Repository\WalletRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Exception\ShopException;
use DateTime;
use DateTimeZone;

class RewardManager
{
    public const CHALLENGES = [
        'play' => ['metric' => 'played', 'target' => 3, 'setting' => 'challenge_play'],
        'win' => ['metric' => 'won', 'target' => 1, 'setting' => 'challenge_win'],
        'variety' => ['metric' => 'games', 'target' => 2, 'setting' => 'challenge_variety'],
    ];
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        protected WalletManager $wallets,
        protected WalletRepository $repository,
        protected SettingManager $settings,
    ) {
    }

    public function daily(User $user): array
    {
        $wallet = $this->wallets->wallet($user);
        $today = $this->today();
        $last = $wallet->getDailyDay()?->format('Y-m-d');
        $claimed = $last === $today->format('Y-m-d');
        $continues = $claimed || $last === (clone $today)->modify('-1 day')->format('Y-m-d');
        $streak = $continues ? $wallet->getDailyStreak() : 0;
        $rewards = $this->dailyRewards();
        $next = $claimed ? $streak : $streak + 1;

        return [
            'claimed' => $claimed,
            'streak' => $streak,
            'day' => (($next - 1) % count($rewards)) + 1,
            'amount' => $rewards[($next - 1) % count($rewards)],
            'rewards' => $rewards,
        ];
    }

    public function claimDaily(User $user): int
    {
        $status = $this->daily($user);

        if ($status['claimed']) {
            throw new ShopException();
        }

        $wallet = $this->wallets->wallet($user);
        $this->repository->save($wallet->setDailyDay($this->today())->setDailyStreak($status['streak'] + 1), true);
        $this->wallets->credit($user, $status['amount'], 'daily', 'Jour ' . $status['day']);

        return $status['amount'];
    }

    public function challenges(User $user): array
    {
        $progress = $this->progress($user);
        $claimed = $this->claimed($user);
        $list = [];

        foreach (self::CHALLENGES as $code => $challenge) {
            $value = min($challenge['target'], $progress[$challenge['metric']]);
            $list[$code] = [
                'code' => $code,
                'target' => $challenge['target'],
                'value' => $value,
                'reward' => (int) $this->settings->get($challenge['setting']),
                'done' => $value >= $challenge['target'],
                'claimed' => in_array($code, $claimed, true),
            ];
        }

        return $list;
    }

    public function claimChallenge(User $user, string $code): int
    {
        $challenge = $this->challenges($user)[$code] ?? null;

        if ($challenge === null || !$challenge['done'] || $challenge['claimed'] || $challenge['reward'] <= 0) {
            throw new ShopException();
        }

        $wallet = $this->wallets->wallet($user);
        $this->repository->save($wallet->setChallenges(['day' => $this->today()->format('Y-m-d'), 'codes' => [...$this->claimed($user), $code]]), true);
        $this->wallets->credit($user, $challenge['reward'], 'challenge', $code);

        return $challenge['reward'];
    }

    public function pending(User $user): int
    {
        $count = $this->daily($user)['claimed'] ? 0 : 1;

        foreach ($this->challenges($user) as $challenge) {
            $count += $challenge['done'] && !$challenge['claimed'] ? 1 : 0;
        }

        return $count;
    }

    public function dailyRewards(): array
    {
        $values = array_values(array_filter(array_map('intval', explode(',', (string) $this->settings->get('daily_rewards'))), fn (int $value): bool => $value > 0));

        return $values === [] ? [10] : $values;
    }

    protected function progress(User $user): array
    {
        $row = $this->repository->getOrm()->getConnection()->fetchAssociative(
            'SELECT COUNT(*) AS played, COALESCE(SUM(won), 0) AS won, COUNT(DISTINCT game_id) AS games FROM game_result WHERE user_id = :user AND created_at >= :since',
            ['user' => $user->getId(), 'since' => $this->today()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s')],
        );

        return array_map('intval', $row ?? ['played' => 0, 'won' => 0, 'games' => 0]);
    }

    protected function claimed(User $user): array
    {
        $data = $this->wallets->wallet($user)->getChallenges();

        return ($data['day'] ?? null) === $this->today()->format('Y-m-d') ? (array) ($data['codes'] ?? []) : [];
    }

    protected function today(): DateTime
    {
        return new DateTime('today', new DateTimeZone(self::TIMEZONE));
    }
}
