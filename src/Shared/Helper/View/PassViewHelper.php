<?php

namespace App\Shared\Helper\View;

use App\Entity\ShopProduct;
use App\Shared\Engage\SeasonPassManager;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class PassViewHelper implements ViewFunctionInterface
{
    public function __construct(
        protected SeasonPassManager $passes,
    ) {
    }

    public function getName(): string
    {
        return 'pass_track_names';
    }

    public function __invoke(ShopProduct $product): array
    {
        $chosen = (array) $product->option('pass_tracks', $product->option('season_pass') === true ? ['premium'] : []);

        return array_values(array_map(fn (array $track): string => $track['name'], array_filter($this->passes->tracks(), fn (array $track): bool => in_array($track['key'], $chosen, true))));
    }
}
