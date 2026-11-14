<?php

namespace App\Shared\Helper\View;

use App\Shared\Pagination\Pagination;
use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class PaginateViewHelper implements ViewFunctionInterface
{
    public function __construct(protected ContainerManagerInterface $container)
    {
    }

    public function getName(): string
    {
        return 'paginate';
    }

    public function __invoke(iterable $items, string $name = 'page', int $size = Pagination::SIZE): Pagination
    {
        $request = $this->container->bound(Request::class) ? $this->container->get(Request::class) : null;

        return Pagination::slice($items, (int) ($request?->query->get($name, 1) ?? 1), $size, $name);
    }
}
