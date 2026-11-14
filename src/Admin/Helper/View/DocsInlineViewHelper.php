<?php

namespace App\Admin\Helper\View;

use NeoPHP\Component\View\Contract\ViewFilterInterface;
use NeoPHP\Component\View\Contract\ViewSafeHtmlInterface;

class DocsInlineViewHelper implements ViewFilterInterface, ViewSafeHtmlInterface
{
    public function getName(): string
    {
        return 'doc_inline';
    }

    public function __invoke(?string $text): string
    {
        $html = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $html = (string) preg_replace('/`([^`]+)`/', '<code class="docs__code">$1</code>', $html);

        return (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
    }
}
