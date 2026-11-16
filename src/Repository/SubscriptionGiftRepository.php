<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionGift;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SubscriptionGiftRepository extends AbstractRepository
{
    protected string $entityClass = SubscriptionGift::class;
}
