<?php

namespace App\Shared\Helper\View;

use App\Entity\User;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\WalletManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

class WalletViewHelper implements ViewFunctionInterface
{
    protected ?array $wallet = null;

    public function __construct(
        protected SecurityManagerInterface $security,
        protected WalletManager $wallets,
        protected LifeManager $lives,
    ) {
    }

    public function getName(): string
    {
        return 'wallet';
    }

    public function __invoke(): ?array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User || $user->isGuest()) {
            return null;
        }

        return $this->wallet ??= ['coins' => $this->wallets->balance($user), ...$this->lives->status($user)];
    }
}
