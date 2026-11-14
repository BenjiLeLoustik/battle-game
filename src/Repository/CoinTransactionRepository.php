<?php

namespace App\Repository;

use App\Entity\CoinTransaction;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class CoinTransactionRepository extends AbstractRepository
{
    protected string $entityClass = CoinTransaction::class;
}
