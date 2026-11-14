<?php

namespace App\Shared\Helper\View;

use App\Shared\Content\ContentTranslationManager;
use NeoPHP\Component\View\Contract\ViewFilterInterface;

class LocalizeViewHelper implements ViewFilterInterface
{
    public function __construct(protected ContentTranslationManager $contents)
    {
    }

    public function getName(): string
    {
        return 'localize';
    }

    public function __invoke(?object $entity, string $field): mixed
    {
        return $entity === null ? null : $this->contents->localize($entity, $field);
    }
}
