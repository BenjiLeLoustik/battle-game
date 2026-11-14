<?php

namespace App\Repository;

use App\Entity\Category;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class CategoryRepository extends AbstractRepository
{
    protected string $entityClass = Category::class;

    public function findOrdered(bool $active = false): array
    {
        return $this->findBy($active ? ['active' => true] : [], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
