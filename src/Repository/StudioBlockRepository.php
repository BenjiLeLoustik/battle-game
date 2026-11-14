<?php

namespace App\Repository;

use App\Entity\StudioBlock;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class StudioBlockRepository extends AbstractRepository
{
    protected string $entityClass = StudioBlock::class;

    public function findOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}
