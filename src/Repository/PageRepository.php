<?php

namespace App\Repository;

use App\Entity\Page;
use App\Shared\Page\Enum\PageFooterEnum;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class PageRepository extends AbstractRepository
{
    protected string $entityClass = Page::class;

    public function findOrdered(): array
    {
        return $this->findBy([], ['footer' => 'ASC', 'position' => 'ASC', 'title' => 'ASC']);
    }

    public function findPublished(string $path): ?Page
    {
        return $this->findOneBy(['path' => $path, 'published' => true]);
    }

    public function findByPath(string $path): ?Page
    {
        return $this->findOneBy(['path' => $path]);
    }

    public function footer(): array
    {
        $columns = [];

        foreach ($this->findBy(['published' => true], ['position' => 'ASC', 'title' => 'ASC']) as $page) {
            if ($page->getFooter() !== PageFooterEnum::NONE) {
                $columns[$page->getFooter()->value][] = $page;
            }
        }

        return $columns;
    }
}
