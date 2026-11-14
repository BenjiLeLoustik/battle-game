<?php

namespace App\Shared\Provider;

use App\Shared\Translation\TranslationOverrides;
use App\Shared\Translation\Translator;
use NeoPHP\Component\Config\ConfigManagerInterface;
use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Component\Container\Contract\AbstractProvider;
use NeoPHP\Package\Translation\TranslationManagerInterface;

class TranslationProvider extends AbstractProvider
{
    public function register(ContainerManagerInterface $container): void
    {
        $overrides = new TranslationOverrides((string) $container->get('kernel.root_path'));
        $container->get(ConfigManagerInterface::class)->set('packages.translation.locales', $overrides->locales());
    }

    public function boot(ContainerManagerInterface $container): void
    {
        $translator = $container->get(TranslationManagerInterface::class);
        (new TranslationOverrides((string) $container->get('kernel.root_path')))->apply($translator);
        Translator::setManager($translator);
    }
}
