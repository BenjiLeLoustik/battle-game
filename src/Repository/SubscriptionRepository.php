<?php

namespace App\Repository;

use App\Entity\Subscription;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SubscriptionRepository extends AbstractRepository
{
    protected string $entityClass = Subscription::class;
}
