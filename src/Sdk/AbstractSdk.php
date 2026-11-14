<?php

namespace App\Sdk;

use App\Sdk\Contract\GameManagerInterface;
use App\Sdk\Trait\ClockTrait;

abstract class AbstractSdk implements GameManagerInterface
{
    use ClockTrait;

    public const VERSION = '1.0';
    protected array $manifest = [];
    protected array $languages = [];
    protected array $contents = [];

    public function setManifest(array $manifest): static
    {
        $this->manifest = $manifest;

        return $this;
    }

    public function setLanguage(string $locale, string $file): static
    {
        $this->languages[$locale] = ltrim($file, '/');

        return $this;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function setContents(array $contents): static
    {
        $this->contents = $contents;

        return $this;
    }

    public function getContent(string $key): array
    {
        return array_filter(array_map(fn (array $content): mixed => $content[$key] ?? null, $this->contents), fn (mixed $value): bool => $value !== null);
    }

    public function configure(array $settings): array
    {
        $values = [];

        foreach ($this->manifest['settings'] ?? [] as $setting) {
            $value = $settings[$setting['name']] ?? $setting['default'];
            $value = is_int($setting['default']) ? (int) $value : $value;
            $values[$setting['name']] = in_array($value, $setting['choices'] ?? [$value], true)
                ? $value
                : $setting['default'];
        }

        return $values;
    }
}