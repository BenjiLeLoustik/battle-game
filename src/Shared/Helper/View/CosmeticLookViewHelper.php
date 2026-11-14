<?php

namespace App\Shared\Helper\View;

use App\Entity\ShopProduct;
use App\Shared\Shop\CosmeticDesign;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class CosmeticLookViewHelper implements ViewFunctionInterface
{
    public function getName(): string
    {
        return 'cosmetic_look';
    }

    public function __invoke(ShopProduct $product): array
    {
        return CosmeticDesign::look($product);
    }
}
