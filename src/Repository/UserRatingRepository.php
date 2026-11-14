<?php

namespace App\Repository;

use App\Entity\UserRating;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class UserRatingRepository extends AbstractRepository
{
    protected string $entityClass = UserRating::class;
}
