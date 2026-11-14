<?php

namespace App\Repository;

use App\Entity\Setting;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SettingRepository extends AbstractRepository
{
    protected string $entityClass = Setting::class;
}
