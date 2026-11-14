<?php

namespace App\Repository;

use App\Entity\MailingList;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class MailingListRepository extends AbstractRepository
{
    protected string $entityClass = MailingList::class;
}
