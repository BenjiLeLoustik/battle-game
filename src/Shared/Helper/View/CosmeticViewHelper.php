<?php

namespace App\Shared\Helper\View;

use App\Shared\Shop\ShopManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class CosmeticViewHelper implements ViewFunctionInterface
{
    public function __construct(protected ShopManager $shop)
    {
    }

    public function getName(): string
    {
        return 'cosmetics';
    }

    public function __invoke(mixed $user): array
    {
        $id = is_object($user) && method_exists($user, 'getId') ? $user->getId() : (is_array($user) ? ($user['id'] ?? null) : null);

        return is_numeric($id) ? $this->shop->cosmetics((int) $id) : [];
    }
}
