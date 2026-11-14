<?php

namespace App\Repository;

use App\Entity\TournamentEntry;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class TournamentEntryRepository extends AbstractRepository
{
    protected string $entityClass = TournamentEntry::class;
}
