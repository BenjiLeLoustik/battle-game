<?php

namespace App\Repository;

use App\Entity\Wallet;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class WalletRepository extends AbstractRepository
{
    protected string $entityClass = Wallet::class;
}
