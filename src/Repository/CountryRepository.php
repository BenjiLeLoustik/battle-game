<?php

namespace App\Repository;

use App\Entity\Country;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class CountryRepository extends AbstractRepository
{
    protected string $entityClass = Country::class;

    public function findOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function findByCode(?string $code): ?Country
    {
        return $code === null || $code === '' ? null : $this->findOneBy(['code' => strtoupper($code)]);
    }

    public function codes(): array
    {
        return array_map(fn (Country $country): string => $country->getCode(), $this->findOrdered());
    }

    public function findForShipping(): array
    {
        return $this->findBy(['shippingEnabled' => true], ['name' => 'ASC']);
    }

    public function shippingCodes(): array
    {
        return array_map(fn (Country $country): string => $country->getCode(), $this->findForShipping());
    }

    public function canShipTo(?string $code): bool
    {
        return $this->findByCode($code)?->isShippingEnabled() ?? false;
    }
}
