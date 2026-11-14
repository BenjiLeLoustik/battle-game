<?php

namespace App\Shared\Translation;

use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Package\Translation\TranslationManagerInterface;

class TranslationOverrides
{
    public const REFERENCE = 'fr';
    public const DIRECTORY = 'var/translations';
    private const DISABLED = 'disabled.json';

    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function base(): string
    {
        return $this->rootPath . '/translations';
    }

    public function directory(): string
    {
        return $this->rootPath . '/' . self::DIRECTORY;
    }

    public function locales(): array
    {
        $files = [...glob($this->base() . '/*.*.yaml') ?: [], ...glob($this->directory() . '/*.*.yaml') ?: []];
        $locales = array_diff(array_unique([self::REFERENCE, ...array_map(fn (string $file): string => explode('.', basename($file))[1], $files)]), $this->disabled());
        usort($locales, fn (string $a, string $b): int => [$a !== self::REFERENCE, $a] <=> [$b !== self::REFERENCE, $b]);

        return array_values($locales);
    }

    public function disabled(): array
    {
        $file = $this->directory() . '/' . self::DISABLED;

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    public function disable(string $locale, bool $disabled): void
    {
        $locales = array_values(array_unique($disabled ? [...$this->disabled(), $locale] : array_diff($this->disabled(), [$locale])));
        $this->prepare();
        file_put_contents($this->directory() . '/' . self::DISABLED, json_encode($locales));
    }

    public function file(string $domain, string $locale): string
    {
        return $this->directory() . '/' . $domain . '.' . $locale . '.yaml';
    }

    public function files(?string $locale = null): array
    {
        return glob($this->directory() . '/*.' . ($locale ?? '*') . '.yaml') ?: [];
    }

    public function apply(TranslationManagerInterface $translator): void
    {
        foreach ($this->files() as $file) {
            [$domain, $locale] = explode('.', basename($file));

            if (!in_array($locale, $this->disabled(), true)) {
                $translator->addMessages($translator->loadFile($file), $locale, $domain);
            }
        }
    }

    public function prepare(): void
    {
        if (!is_dir($this->directory())) {
            mkdir($this->directory(), 0775, true);
        }
    }
}
