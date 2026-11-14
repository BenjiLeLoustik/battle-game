<?php

namespace App\Shared\Badge;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Repository\BadgeRepository;
use App\Repository\GameRepository;
use App\Repository\UserBadgeRepository;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\Rule\AllGamesPlayedRule;
use App\Shared\Badge\Rule\FriendsCountRule;
use App\Shared\Badge\Rule\GamesPlayedRule;
use App\Shared\Badge\Rule\LevelReachedRule;
use App\Shared\Badge\Rule\ScoreThresholdRule;
use App\Shared\Badge\Rule\WinsRule;
use App\Shared\Badge\Rule\WinStreakRule;
use App\Shared\Content\ContentTranslationManager;
use DateTime;

class BadgeManager
{
    public const RULES = [
        GamesPlayedRule::class,
        WinsRule::class,
        LevelReachedRule::class,
        WinStreakRule::class,
        FriendsCountRule::class,
        AllGamesPlayedRule::class,
        ScoreThresholdRule::class,
    ];

    public const GENERAL = [
        ['code' => 'general.first_game', 'name' => 'Première partie', 'description' => 'Jouer une partie', 'rule' => 'games_played', 'params' => ['count' => 1], 'design' => ['rarity' => 'common', 'shape' => 'hexagon', 'icon' => 'play']],
        ['code' => 'general.games_100', 'name' => 'Centurion', 'description' => 'Jouer 100 parties', 'rule' => 'games_played', 'params' => ['count' => 100], 'design' => ['rarity' => 'rare', 'shape' => 'hexagon', 'icon' => 'play', 'label' => '100']],
        ['code' => 'general.first_win', 'name' => 'Première victoire', 'description' => 'Gagner une partie', 'rule' => 'wins', 'params' => ['count' => 1], 'design' => ['rarity' => 'common', 'shape' => 'circle', 'icon' => 'trophy']],
        ['code' => 'general.level_25', 'name' => 'Vétéran', 'description' => 'Atteindre le niveau 25', 'rule' => 'level_reached', 'params' => ['level' => 25], 'design' => ['rarity' => 'epic', 'shape' => 'shield', 'icon' => 'medal', 'label' => '25']],
        ['code' => 'general.streak_5', 'name' => 'En feu', 'description' => '5 victoires d\'affilée', 'rule' => 'win_streak', 'params' => ['streak' => 5], 'design' => ['rarity' => 'rare', 'shape' => 'diamond', 'icon' => 'flame', 'color' => '#FF9F43']],
        ['code' => 'general.friends_10', 'name' => 'Bonne équipe', 'description' => 'Avoir 10 amis', 'rule' => 'friends_count', 'params' => ['count' => 10], 'design' => ['rarity' => 'common', 'shape' => 'circle', 'icon' => 'heart', 'color' => '#FF7FDD']],
        ['code' => 'general.all_games', 'name' => 'Touche-à-tout', 'description' => 'Jouer à tous les jeux en ligne', 'rule' => 'all_games_played', 'params' => [], 'design' => ['rarity' => 'epic', 'shape' => 'octagon', 'icon' => 'grid']],
    ];

    protected ?array $rules = null;

    public function __construct(
        protected BadgeRepository $badges,
        protected UserBadgeRepository $userBadges,
        protected GameRepository $games,
        protected BadgeStats $stats,
        protected ContentTranslationManager $contents,
    ) {
    }

    public function rules(): array
    {
        if ($this->rules === null) {
            $this->rules = [];

            foreach (self::RULES as $class) {
                $rule = new $class();
                $this->rules[$rule->getName()] = $rule;
            }
        }

        return $this->rules;
    }

    public function rule(string $name): ?BadgeRuleInterface
    {
        if (isset($this->rules()[$name])) {
            return $this->rules[$name];
        }

        return class_exists($name) && is_subclass_of($name, BadgeRuleInterface::class) ? new $name() : null;
    }

    public function evaluate(User $user, array $games = []): array
    {
        $this->stats->reset();
        $obtained = $this->userBadges->obtained($user);
        $candidates = $this->badges->findGeneral();

        foreach ($games as $game) {
            $candidates = [...$candidates, ...$this->badges->findForGame($game)];
        }

        $earned = [];

        foreach ($candidates as $badge) {
            if (isset($obtained[$badge->getId()]) || $this->percent($badge, $user) < 100) {
                continue;
            }

            $this->userBadges->save((new UserBadge())->setUser($user)->setBadge($badge));
            $earned[] = $this->view($badge, 'on', 100, new DateTime());
        }

        if ($earned !== []) {
            $this->userBadges->getOrm()->flush();
        }

        return $earned;
    }

    public function playedGames(User $user): array
    {
        $ids = $this->badges->getOrm()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT game_id FROM game_result WHERE user_id = :user',
            ['user' => $user->getId()],
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?Game => $this->games->find((int) $id), $ids)));
    }

    public function collection(User $user, ?Game $game, bool $owner): array
    {
        $this->stats->reset();
        $obtained = $this->userBadges->obtained($user);
        $list = $game === null ? $this->badges->findGeneral() : $this->badges->findForGame($game);
        $views = [];

        foreach ($list as $badge) {
            $userBadge = $obtained[$badge->getId()] ?? null;

            if ($userBadge !== null) {
                $views[] = $this->view($badge, 'on', 100, $userBadge->getCreatedAt());
            } elseif ($badge->isSecret()) {
                $views[] = $this->view($badge, 'secret');
            } else {
                $percent = $owner ? $this->percent($badge, $user) : 0;
                $views[] = $this->view($badge, $percent > 0 ? 'progress' : 'locked', $percent);
            }
        }

        return $views;
    }

    public function obtained(User $user): array
    {
        return array_map(
            fn (UserBadge $userBadge): array => $this->view($userBadge->getBadge(), 'on', 100, $userBadge->getCreatedAt()),
            array_values($this->userBadges->obtained($user)),
        );
    }

    public function featured(User $user, int $limit): array
    {
        $order = array_flip(BadgeDesign::RARITIES);
        $obtained = $this->obtained($user);
        $showcase = array_flip($user->getShowcase());
        usort($obtained, fn (array $a, array $b): int => ($showcase[$a['id']] ?? 99) <=> ($showcase[$b['id']] ?? 99)
            ?: ($b['id'] === $user->getFeaturedBadge()?->getId()) <=> ($a['id'] === $user->getFeaturedBadge()?->getId())
            ?: $order[$b['design']['rarity']] <=> $order[$a['design']['rarity']]);

        return array_slice($obtained, 0, $limit);
    }

    public function owns(User $user, Badge $badge): bool
    {
        return isset($this->userBadges->obtained($user)[$badge->getId()]);
    }

    public function view(Badge $badge, string $state = 'on', int $percent = 100, ?DateTime $obtainedAt = null): array
    {
        $design = BadgeDesign::normalize($badge->getDesign(), $badge->getGame()?->getColor());
        $secret = $state === 'secret';

        return [
            'id' => $badge->getId(),
            'code' => $badge->getCode(),
            'name' => $this->contents->localize($badge, 'name'),
            'description' => $this->contents->localize($badge, 'description'),
            'game' => $badge->getGame() === null ? null : $this->contents->localize($badge->getGame(), 'name'),
            'design' => $design,
            'path' => BadgeDesign::path($design, $secret),
            'state' => $state,
            'percent' => $percent,
            'obtainedAt' => $obtainedAt?->format(DATE_ATOM),
        ];
    }

    public function sync(): int
    {
        $count = 0;

        foreach (self::GENERAL as $position => $definition) {
            $this->define(null, $definition, $position);
            $count++;
        }

        foreach ($this->games->findAll() as $game) {
            $count += $this->syncGame($game);
        }

        $this->badges->getOrm()->flush();

        return $count;
    }

    public function syncGame(Game $game): int
    {
        $definitions = $game->getManifest()['badges'] ?? [];

        foreach ($definitions as $position => $definition) {
            $this->define($game, ['code' => $game->getSlug() . '.' . ($definition['code'] ?? $position)] + $definition, $position);
        }

        $this->badges->getOrm()->flush();

        return count($definitions);
    }

    protected function define(?Game $game, array $definition, int $position): void
    {
        $badge = $this->badges->findOneBy(['code' => $definition['code']]) ?? (new Badge())->setCode($definition['code']);

        $this->badges->save($badge
            ->setGame($game)
            ->setName((string) ($definition['name'] ?? $definition['code']))
            ->setDescription((string) ($definition['description'] ?? ''))
            ->setRule((string) ($definition['rule'] ?? 'games_played'))
            ->setParams((array) ($definition['params'] ?? []))
            ->setDesign((array) ($definition['design'] ?? []))
            ->setSecret((bool) ($definition['secret'] ?? false))
            ->setPosition($position));
    }

    protected function percent(Badge $badge, User $user): int
    {
        $rule = $this->rule($badge->getRule());

        if ($rule === null) {
            return 0;
        }

        [$current, $target] = $rule->progress($this->stats, $user, $badge->getGame(), $badge->getParams());

        return (int) min(100, floor($current / max($target, 0.0001) * 100));
    }
}
