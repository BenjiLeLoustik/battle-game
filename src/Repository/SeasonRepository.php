<?php

namespace App\Repository;

use App\Entity\Season;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SeasonRepository extends AbstractRepository
{
    protected string $entityClass = Season::class;

    public function current(): ?Season
    {
        $id = $this->getOrm()->getConnection()->fetchOne(
            'SELECT id FROM season WHERE starts_at <= :now AND ends_at > :now ORDER BY starts_at DESC LIMIT 1',
            ['now' => date('Y-m-d H:i:s')],
        );

        return $id !== null && $id !== false ? $this->find((int) $id) : null;
    }
}