<?php

namespace App\Shared\Helper\View;

use App\Entity\User;
use App\Shared\Banner\BannerManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

class BannerViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected BannerManager $banners,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function getName(): string
    {
        return 'site_banners';
    }

    public function __invoke(?string $route = null): array
    {
        $user = $this->security->getUser();

        return $this->banners->visible($user instanceof User ? $user : null, $route);
    }
}
