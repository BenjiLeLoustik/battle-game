<?php

namespace App\Admin\Helper\View;

use App\Shared\Content\ContentTranslationManager;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class ContentTranslationsViewHelper implements ViewFunctionInterface
{
    public function __construct(protected ContentTranslationManager $contents)
    {
    }

    public function getName(): string
    {
        return 'content_translations';
    }

    public function __invoke(TranslatableInterface $entity): array
    {
        return [
            'locales' => $this->contents->targets(),
            'fields' => $entity::getTranslatableFields(),
            'values' => $this->contents->values($entity),
            'sources' => array_combine(
                array_keys($entity::getTranslatableFields()),
                array_map(fn (string $field): string => (string) $entity->{'get' . ucfirst($field)}(), array_keys($entity::getTranslatableFields()))
            ),
        ];
    }
}
