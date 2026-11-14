<?php

namespace App\Controller\Admin;

use App\Admin\Maintenance\MaintenanceRequest;
use App\Entity\Maintenance;
use App\Repository\GameRepository;
use App\Repository\MaintenanceRepository;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Maintenance\Enum\MaintenanceFeatureEnum;
use App\Shared\Maintenance\Enum\MaintenanceMatchPolicyEnum;
use App\Shared\Maintenance\Enum\MaintenancePageEnum;
use App\Shared\Maintenance\Enum\MaintenanceStatusEnum;
use App\Shared\Maintenance\Enum\MaintenanceTargetEnum;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/maintenance', name: 'maintenance_')]
class MaintenanceController extends AbstractAdminController
{
    private const TABS = [
        'open' => [MaintenanceStatusEnum::PLANNED, MaintenanceStatusEnum::RUNNING],
        'running' => [MaintenanceStatusEnum::RUNNING],
        'history' => [MaintenanceStatusEnum::DONE, MaintenanceStatusEnum::CANCELLED],
    ];

    public function __construct(
        protected MaintenanceRepository $maintenances,
        protected GameRepository $games,
        protected NotificationManager $notifications
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tab = array_key_exists((string) $request->query->get('tab'), self::TABS) ? (string) $request->query->get('tab') : 'open';
        $running = $this->maintenances->findByStatuses([MaintenanceStatusEnum::RUNNING]);
        $planned = $this->maintenances->findByStatuses([MaintenanceStatusEnum::PLANNED]);
        $ofTarget = fn (array $list, MaintenanceTargetEnum $target): array => array_values(array_filter($list, fn (Maintenance $maintenance): bool => $maintenance->getTarget() === $target));

        return $this->render('@admin/pages/maintenance/index.html.twig', [
            'tab' => $tab,
            'counts' => array_map(fn (array $statuses): int => count($this->maintenances->findByStatuses($statuses)), self::TABS),
            'maintenances' => array_reverse($this->maintenances->findByStatuses(self::TABS[$tab])),
            'site' => [
                'running' => $ofTarget($running, MaintenanceTargetEnum::SITE)[0] ?? null,
                'next' => $ofTarget($planned, MaintenanceTargetEnum::SITE)[0] ?? null
            ],
            'gameMaintenances' => $ofTarget($running, MaintenanceTargetEnum::GAMES),
            'featureMaintenances' => $ofTarget($running, MaintenanceTargetEnum::FEATURES),
            'studioGames' => $this->games->findBy(['status' => GameStatusEnum::MAINTENANCE]),
            'gameNames' => $this->gameNames(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('maintenance')]
    public function new(Request $request): Response
    {
        $game = $this->games->findOneBy(['slug' => (string) $request->query->get('game')]);
        $data = $request->getMethod() === 'POST'
            ? MaintenanceRequest::fromArray($request->request->all())
            : MaintenanceRequest::create($game ? 'Maintenance ' . $game->getName() : null, $game ? [$game->getId()] : [], $request->query->get('now') === '1');

        return $this->handle($request, $data, (new Maintenance())->setAuthor($this->getUser()), $game?->getName());
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Csrf('maintenance')]
    public function edit(int $id, Request $request): Response
    {
        $maintenance = $this->find($id);
        $data = $request->getMethod() === 'POST'
            ? MaintenanceRequest::fromArray($request->request->all())
            : MaintenanceRequest::fromMaintenance($maintenance);

        return $this->handle($request, $data, $maintenance);
    }

    #[Route('/{id}/end', name: 'end', methods: ['POST'])]
    #[Csrf('maintenance')]
    public function end(int $id): Response
    {
        $maintenance = $this->find($id);

        if ($maintenance->isRunning()) {
            $this->maintenances->save($maintenance->end(), true);
        }

        return $this->redirectToRoute('admin_maintenance_index');
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[Csrf('maintenance')]
    public function cancel(int $id): Response
    {
        $maintenance = $this->find($id);

        if ($maintenance->getStatus() === MaintenanceStatusEnum::PLANNED) {
            $this->maintenances->save($maintenance->cancel(), true);
        }

        return $this->redirectToRoute('admin_maintenance_index');
    }

    protected function handle(
        Request $request,
        MaintenanceRequest $data,
        Maintenance $maintenance,
        ?string $preset = null
    ): Response {
        $errors = $request->getMethod() === 'POST' ? $data->errors() : [];

        if ($request->getMethod() === 'POST' && $errors === []) {
            $created = $maintenance->getId() === null;
            $this->maintenances->save($data->applyTo($maintenance), true);
            $this->saveTranslations($maintenance, $request);

            if ($created && $maintenance->getStartsAt() > new DateTime()) {
                $this->notifications->broadcast(NotificationTypeEnum::MAINTENANCE_PLANNED, ['title' => $maintenance->getTitle(), 'date' => $maintenance->getStartsAt()->format('d/m H:i')]);
            }

            return $this->redirectToRoute('admin_maintenance_index');
        }

        return $this->render('@admin/pages/maintenance/form.html.twig', [
            'maintenance' => $maintenance,
            'data' => $data,
            'errors' => $errors,
            'preset' => $preset,
            'targets' => MaintenanceTargetEnum::cases(),
            'features' => MaintenanceFeatureEnum::cases(),
            'pages' => MaintenancePageEnum::cases(),
            'policies' => MaintenanceMatchPolicyEnum::cases(),
            'notices' => MaintenanceRequest::NOTICES,
            'games' => $this->games->findBy([], ['name' => 'ASC']),
        ]);
    }

    protected function gameNames(): array
    {
        $names = [];

        foreach ($this->games->findAll() as $game) {
            $names[$game->getId()] = $game->getName();
        }

        return $names;
    }

    protected function find(int $id): Maintenance
    {
        return $this->maintenances->find($id) ?? throw $this->createNotFoundException();
    }
}