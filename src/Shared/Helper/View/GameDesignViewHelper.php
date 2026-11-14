<?php

namespace App\Shared\Helper\View;

use App\Entity\Game;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class GameDesignViewHelper implements ViewFunctionInterface
{
    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function getName(): string
    {
        return 'game_design';
    }

    public function __invoke(Game $game): ?string
    {
        $file = $this->rootPath . '/' . GameManager::PUBLIC_DIRECTORY . '/' . basename($game->getSlug()) . '/css/design.css';

        return is_file($file) && filesize($file) > 0 ? $game->getAssetUrl('css/design.css') . '?v=' . filemtime($file) : null;
    }
}
