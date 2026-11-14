<?php

namespace App\Shared\Helper\View;

use NeoPHP\Component\View\Contract\ViewFilterInterface;
use NeoPHP\Package\Translation\Exception\TranslationException;
use NeoPHP\Package\Translation\TranslationManagerInterface;

class LanguageNameViewHelper implements ViewFilterInterface
{
    public function __construct(protected TranslationManagerInterface $translator)
    {
    }

    public function getName(): string
    {
        return 'language_name';
    }

    public function __invoke(string $locale, ?string $display = null): string
    {
        try {
            $name = $this->translator->getLanguageName($locale, $display ?? $locale);
        } catch (TranslationException) {
            return strtoupper($locale);
        }

        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }
}
