<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LevelDefinition;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class LevelDefinitionRepository extends AbstractRepository
{
    protected string $entityClass = LevelDefinition::class;
}
