<?php

namespace App\Admin\Maintenance;

use App\Entity\Maintenance;
use App\Shared\Maintenance\Enum\MaintenanceFeatureEnum;
use App\Shared\Maintenance\Enum\MaintenanceMatchPolicyEnum;
use App\Shared\Maintenance\Enum\MaintenancePageEnum;
use App\Shared\Maintenance\Enum\MaintenanceTargetEnum;
use DateTime;

class MaintenanceRequest
{
    public const NOTICES = [1440, 180, 60, 0];
    private const FORMAT = 'Y-m-d\TH:i';

    public string $title = '';
    public MaintenanceTargetEnum $target = MaintenanceTargetEnum::SITE;
    public array $games = [];
    public array $features = [];
    public string $startsAt = '';
    public string $endsAt = '';
    public MaintenanceMatchPolicyEnum $matchPolicy = MaintenanceMatchPolicyEnum::FINISH;
    public bool $blockBefore = true;
    public array $pages = [];
    public string $paths = '';
    public int $notice = 1440;
    public string $message = '';

    public static function fromArray(array $data): static
    {
        $request = new static();
        $request->title = trim((string) ($data['title'] ?? ''));
        $request->target = MaintenanceTargetEnum::tryFrom((string) ($data['target'] ?? '')) ?? MaintenanceTargetEnum::SITE;
        $request->games = array_map('intval', (array) ($data['games'] ?? []));
        $request->features = array_values(array_filter((array) ($data['features'] ?? []), fn (mixed $value): bool => MaintenanceFeatureEnum::tryFrom((string) $value) !== null));
        $request->startsAt = (string) ($data['starts_at'] ?? '');
        $request->endsAt = (string) ($data['ends_at'] ?? '');
        $request->matchPolicy = MaintenanceMatchPolicyEnum::tryFrom((string) ($data['match_policy'] ?? '')) ?? MaintenanceMatchPolicyEnum::FINISH;
        $request->blockBefore = isset($data['block_before']);
        $request->pages = array_values(array_filter((array) ($data['pages'] ?? []), fn (mixed $value): bool => MaintenancePageEnum::tryFrom((string) $value) !== null));
        $request->paths = trim((string) ($data['paths'] ?? ''));
        $request->notice = in_array((int) ($data['notice'] ?? 1440), self::NOTICES, true) ? (int) $data['notice'] : 1440;
        $request->message = trim((string) ($data['message'] ?? ''));

        return $request;
    }

    public static function fromMaintenance(Maintenance $maintenance): static
    {
        $request = new static();
        $request->title = $maintenance->getTitle();
        $request->target = $maintenance->getTarget();
        $request->games = $maintenance->getGames();
        $request->features = $maintenance->getFeatures();
        $request->startsAt = $maintenance->getStartsAt()->format(self::FORMAT);
        $request->endsAt = $maintenance->getEndsAt()->format(self::FORMAT);
        $request->matchPolicy = $maintenance->getMatchPolicy();
        $request->blockBefore = $maintenance->isBlockBefore();
        $request->pages = $maintenance->getPages();
        $request->paths = implode("\n", $maintenance->getPaths());
        $request->notice = $maintenance->getNotice();
        $request->message = $maintenance->getMessage();

        return $request;
    }

    public static function create(?string $title = null, array $games = [], bool $now = false): static
    {
        $request = new static();
        $start = $now ? new DateTime() : new DateTime('tomorrow 23:00');
        $request->title = $title ?? '';
        $request->target = $games === [] ? MaintenanceTargetEnum::SITE : MaintenanceTargetEnum::GAMES;
        $request->games = $games;
        $request->startsAt = $start->format(self::FORMAT);
        $request->endsAt = (clone $start)->modify('+2 hours')->format(self::FORMAT);
        $request->pages = array_map(fn (MaintenancePageEnum $page): string => $page->value, MaintenancePageEnum::cases());
        $request->notice = $now ? 0 : 1440;
        $request->matchPolicy = $now ? MaintenanceMatchPolicyEnum::STOP : MaintenanceMatchPolicyEnum::FINISH;

        return $request;
    }

    public function errors(): array
    {
        $start = DateTime::createFromFormat(self::FORMAT, $this->startsAt);
        $end = DateTime::createFromFormat(self::FORMAT, $this->endsAt);

        return array_filter([
            'title' => $this->title === '' ? 'maintenance.title.not_blank' : null,
            'starts_at' => $start === false ? 'maintenance.date.invalid' : null,
            'ends_at' => $end === false || ($start !== false && $end <= $start) ? 'maintenance.date.order' : null,
            'games' => $this->target === MaintenanceTargetEnum::GAMES && $this->games === [] ? 'maintenance.games.empty' : null,
            'features' => $this->target === MaintenanceTargetEnum::FEATURES && $this->features === [] ? 'maintenance.features.empty' : null,
            'message' => $this->message === '' ? 'maintenance.message.not_blank' : null,
        ]);
    }

    public function applyTo(Maintenance $maintenance): Maintenance
    {
        $paths = array_values(array_filter(array_map(
            fn (string $path): string => '/' . trim($path, " \t/"),
            preg_split('/\R/', $this->paths) ?: [],
        ), fn (string $path): bool => $path !== '/'));

        return $maintenance
            ->setTitle($this->title)
            ->setTarget($this->target)
            ->setGames($this->target === MaintenanceTargetEnum::GAMES ? $this->games : [])
            ->setFeatures($this->target === MaintenanceTargetEnum::FEATURES ? $this->features : [])
            ->setStartsAt(DateTime::createFromFormat(self::FORMAT, $this->startsAt))
            ->setEndsAt(DateTime::createFromFormat(self::FORMAT, $this->endsAt))
            ->setMatchPolicy($this->matchPolicy)
            ->setBlockBefore($this->blockBefore)
            ->setPages($this->target === MaintenanceTargetEnum::SITE ? $this->pages : [])
            ->setPaths($this->target === MaintenanceTargetEnum::SITE ? $paths : [])
            ->setNotice($this->notice)
            ->setMessage($this->message);
    }
}