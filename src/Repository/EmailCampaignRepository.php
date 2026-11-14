<?php

namespace App\Repository;

use App\Entity\EmailCampaign;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class EmailCampaignRepository extends AbstractRepository
{
    protected string $entityClass = EmailCampaign::class;
}
