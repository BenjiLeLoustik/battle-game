<?php

namespace App\Shared\Helper\View;

use App\Entity\User;
use App\Shared\Shop\RewardManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

class RewardViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected SecurityManagerInterface $security,
        protected RewardManager $rewards,
    ) {
    }

    public function getName(): string
    {
        return 'rewards';
    }

    public function __invoke(): ?array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User || $user->isGuest()) {
            return null;
        }

        return ['daily' => $this->rewards->daily($user), 'challenges' => $this->rewards->challenges($user)];
    }
}
