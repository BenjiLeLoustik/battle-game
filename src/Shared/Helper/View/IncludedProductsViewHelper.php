<?php

namespace App\Shared\Helper\View;

use App\Entity\ShopProduct;
use App\Shared\Shop\SubscriptionGiftManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class IncludedProductsViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected SubscriptionGiftManager $gifts,
    ) {
    }

    public function getName(): string
    {
        return 'included_products';
    }

    public function __invoke(ShopProduct $offer): array
    {
        return $this->gifts->productsFor($offer);
    }
}