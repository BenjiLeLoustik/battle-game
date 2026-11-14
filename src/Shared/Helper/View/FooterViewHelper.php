<?php

namespace App\Shared\Helper\View;

use App\Repository\PageRepository;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class FooterViewHelper implements ViewFunctionInterface
{
    public function __construct(protected PageRepository $pages)
    {
    }

    public function getName(): string
    {
        return 'footer_pages';
    }

    public function __invoke(): array
    {
        return $this->pages->footer();
    }
}
