<?php

namespace App\Repository;

use App\Entity\AuditLog;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class AuditLogRepository extends AbstractRepository
{
    protected string $entityClass = AuditLog::class;
}
