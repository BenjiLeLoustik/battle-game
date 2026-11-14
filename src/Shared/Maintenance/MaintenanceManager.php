<?php

namespace App\Shared\Maintenance;

use App\Entity\Game;
use App\Entity\GameMatch;
use App\Entity\Maintenance;
use App\Repository\MaintenanceRepository;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Maintenance\Enum\MaintenanceFeatureEnum;
use App\Shared\Maintenance\Enum\MaintenanceMatchPolicyEnum;
use App\Shared\Maintenance\Enum\MaintenancePageEnum;
use App\Shared\Maintenance\Enum\MaintenanceTargetEnum;

class MaintenanceManager
{
    private const ALWAYS = ['/maintenance', '/admin', '/login', '/logout', '/oauth', '/_wdt', '/_profiler', '/builds'];
    private const MATCHES = ['/play/match', '/play/clock'];

    protected ?array $open = null;

    public function __construct(
        protected MaintenanceRepository $maintenances,
        protected ContentTranslationManager $contents,
    ) {
    }

    public function open(): array
    {
        return $this->open ??= $this->maintenances->findOpen();
    }

    public function running(): array
    {
        return array_values(array_filter($this->open(), fn (Maintenance $maintenance): bool => $maintenance->isRunning()));
    }

    public function site(): ?Maintenance
    {
        return $this->first($this->running(), fn (Maintenance $maintenance): bool => $maintenance->getTarget() === MaintenanceTargetEnum::SITE);
    }

    public function nextSite(): ?Maintenance
    {
        return $this->first($this->open(), fn (Maintenance $maintenance): bool => $maintenance->getTarget() === MaintenanceTargetEnum::SITE && $maintenance->isAnnounced());
    }

    public function forGame(Game $game): ?Maintenance
    {
        return $this->first($this->running(), fn (Maintenance $maintenance): bool => $maintenance->concerns($game));
    }

    public function isGameDown(Game $game): bool
    {
        return $game->getStatus() === GameStatusEnum::MAINTENANCE || $this->forGame($game) !== null;
    }

    public function blocksNewMatches(Game $game): bool
    {
        return $game->getStatus() === GameStatusEnum::MAINTENANCE
            || $this->first($this->open(), fn (Maintenance $maintenance): bool => $maintenance->blocksNewMatches($game)) !== null;
    }

    public function cuts(GameMatch $match): bool
    {
        $maintenance = $this->forGame($match->getGame());

        return $maintenance !== null && $maintenance->getMatchPolicy() === MaintenanceMatchPolicyEnum::STOP;
    }

    public function warning(Game $game): ?array
    {
        $maintenance = $this->first($this->open(), fn (Maintenance $maintenance): bool => $maintenance->concerns($game) && ($maintenance->isAnnounced() || $maintenance->blocksNewMatches($game)));

        return $maintenance === null ? null : [
            'target' => $maintenance->getTarget()->value,
            'title' => $this->contents->localize($maintenance, 'title'),
            'message' => $this->contents->localize($maintenance, 'message'),
            'policy' => $maintenance->getMatchPolicy()->value,
            'startsAt' => $maintenance->getStartsAt()->getTimestamp() * 1000,
            'endsAt' => $maintenance->getFinishAt()->getTimestamp() * 1000,
        ];
    }

    public function feature(string $path): ?Maintenance
    {
        return $this->first($this->running(), fn (Maintenance $maintenance): bool => $maintenance->getTarget() === MaintenanceTargetEnum::FEATURES
            && array_filter($maintenance->getFeatures(), fn (string $feature): bool => $this->matches($path, MaintenanceFeatureEnum::from($feature)->paths())) !== []);
    }

    public function allows(Maintenance $site, string $path): bool
    {
        $pages = array_map(fn (string $page): string => MaintenancePageEnum::from($page)->path(), $site->getPages());
        $matches = $site->getMatchPolicy() === MaintenanceMatchPolicyEnum::FINISH ? self::MATCHES : [];

        return $this->matches($path, [...self::ALWAYS, ...$pages, ...$site->getPaths(), ...$matches]);
    }

    public function announcements(): array
    {
        return array_values(array_filter($this->open(), fn (Maintenance $maintenance): bool => $maintenance->isAnnounced()));
    }

    protected function matches(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $prefix = rtrim($prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function first(array $maintenances, callable $filter): ?Maintenance
    {
        foreach ($maintenances as $maintenance) {
            if ($filter($maintenance)) {
                return $maintenance;
            }
        }

        return null;
    }
}
