<?php

namespace App\Repository;

use App\Entity\Invoice;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class InvoiceRepository extends AbstractRepository
{
    protected string $entityClass = Invoice::class;
}
