<?php

namespace App\Shared\Helper\View;

use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;

class DiscordViewHelper implements ViewFunctionInterface
{
    public function __construct(
        #[Autowire(env: 'DISCORD_INVITE_URL')]
        protected ?string $url = null,
    ) {
    }

    public function getName(): string
    {
        return 'discord_url';
    }

    public function __invoke(): ?string
    {
        return $this->url ?: null;
    }
}
