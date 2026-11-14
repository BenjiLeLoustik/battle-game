<?php

namespace App\Shared\Helper\View;

use App\Entity\Maintenance;
use App\Repository\GameRepository;
use App\Shared\Maintenance\Enum\MaintenanceFeatureEnum;
use App\Shared\Maintenance\Enum\MaintenanceTargetEnum;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Translation\Translator;
use DateTime;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class MaintenanceViewHelper implements ViewFunctionInterface
{
    public function __construct(protected MaintenanceManager $maintenance, protected GameRepository $games)
    {
    }

    public function getName(): string
    {
        return 'maintenance_banner';
    }

    public function __invoke(): array
    {
        return array_map($this->item(...), $this->maintenance->announcements());
    }

    protected function item(Maintenance $maintenance): array
    {
        $running = $maintenance->isRunning();
        $games = array_values(array_filter(array_map(fn (int $id) => $this->games->find($id), $maintenance->getGames())));

        $title = match ($maintenance->getTarget()) {
            MaintenanceTargetEnum::SITE => Translator::trans('site.banner.maintenance.site', [], 'messages'),
            MaintenanceTargetEnum::GAMES => Translator::trans('site.banner.maintenance.games', ['names' => implode(', ', array_map(fn ($game): string => $game->getName(), $games))], 'messages'),
            MaintenanceTargetEnum::FEATURES => Translator::trans('site.banner.maintenance.features', ['names' => implode(', ', array_map(fn (string $feature): string => MaintenanceFeatureEnum::from($feature)->translate(), $maintenance->getFeatures()))], 'messages'),
        };

        return [
            'title' => $title,
            'meta' => $running
                ? Translator::trans('site.banner.maintenance.until', ['time' => $this->when($maintenance->getFinishAt())], 'messages')
                : Translator::trans('site.banner.maintenance.from', ['start' => $this->when($maintenance->getStartsAt()), 'end' => $this->when($maintenance->getFinishAt())], 'messages'),
            'url' => match ($maintenance->getTarget()) {
                MaintenanceTargetEnum::SITE => '/maintenance',
                MaintenanceTargetEnum::GAMES => $games !== [] ? '/games/' . $games[0]->getSlug() : null,
                MaintenanceTargetEnum::FEATURES => null,
            },
            'running' => $running,
        ];
    }

    protected function when(DateTime $date): string
    {
        return $date->format('Y-m-d') === date('Y-m-d') ? $date->format('H:i') : $date->format('d/m H:i');
    }
}
