<?php

namespace App\Repository;

use App\Entity\ContentTranslation;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ContentTranslationRepository extends AbstractRepository
{
    protected string $entityClass = ContentTranslation::class;

    public function map(string $type, string $locale): array
    {
        $map = [];

        foreach ($this->findBy(['type' => $type, 'locale' => $locale]) as $translation) {
            $map[$translation->getObjectId()][$translation->getField()] = $translation->getValue();
        }

        return $map;
    }

    public function forObject(string $type, int $id): array
    {
        return $this->findBy(['type' => $type, 'objectId' => $id]);
    }
}
