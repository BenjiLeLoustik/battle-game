<?php

namespace App\Admin\Helper\View;

use App\Admin\Security\AdminAccess;
use App\Entity\User;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

class AdminViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected AdminAccess $access,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function getName(): string
    {
        return 'admin_sections';
    }

    public function __invoke(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->access->sections($user) : [];
    }
}
