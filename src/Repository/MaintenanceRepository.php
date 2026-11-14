<?php

namespace App\Repository;

use App\Entity\Maintenance;
use App\Shared\Maintenance\Enum\MaintenanceStatusEnum;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class MaintenanceRepository extends AbstractRepository
{
    protected string $entityClass = Maintenance::class;

    public function findByStatuses(array $statuses): array
    {
        return array_values(array_filter(
            $this->findBy([], ['startsAt' => 'ASC']),
            fn (Maintenance $maintenance): bool => in_array($maintenance->getStatus(), $statuses, true),
        ));
    }

    public function findOpen(): array
    {
        return $this->findByStatuses([MaintenanceStatusEnum::PLANNED, MaintenanceStatusEnum::RUNNING]);
    }
}