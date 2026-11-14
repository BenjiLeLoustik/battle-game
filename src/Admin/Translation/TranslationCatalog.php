<?php

namespace App\Admin\Translation;

use App\Entity\User;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Translation\TranslationOverrides;
use NeoPHP\Package\Yaml\YamlManagerInterface;

class TranslationCatalog
{
    public const REFERENCE = ContentTranslationManager::REFERENCE;
    private const LOCK_MINUTES = 30;
    private const LOCKS = 'translation_locks';

    public function __construct(
        protected YamlManagerInterface $yaml,
        protected SettingManager $settings,
        protected ContentTranslationManager $contents,
        protected TranslationOverrides $overrides,
    ) {
    }

    public function domains(): array
    {
        return array_map(fn (string $file): string => explode('.', basename($file))[0], glob($this->directory() . '/*.' . self::REFERENCE . '.yaml') ?: []);
    }

    public function locales(): array
    {
        return $this->contents->locales();
    }

    public function base(string $domain, string $locale): array
    {
        return $this->parse($this->file($domain, $locale));
    }

    public function overrides(string $domain, string $locale): array
    {
        return $this->parse($this->overrides->file($domain, $locale));
    }

    public function messages(string $domain, string $locale): array
    {
        return array_replace($this->base($domain, $locale), $this->overrides($domain, $locale));
    }

    public function stats(string $locale): array
    {
        $stats = [];

        foreach ($this->domains() as $domain) {
            $reference = $this->messages($domain, self::REFERENCE);
            $stats[$domain] = ['total' => count($reference), 'done' => count($reference) - count($this->missing($domain, $locale))];
        }

        return $stats;
    }

    public function missing(string $domain, string $locale): array
    {
        $translated = array_filter($this->messages($domain, $locale), fn (mixed $value): bool => is_string($value) && $value !== '');

        return array_values(array_diff(array_keys($this->messages($domain, self::REFERENCE)), array_keys($translated)));
    }

    public function save(string $domain, string $locale, string $key, string $value): void
    {
        $overrides = $this->overrides($domain, $locale);
        $overrides[$key] = $value;

        if (($this->base($domain, $locale)[$key] ?? null) === $value) {
            unset($overrides[$key]);
        }

        $this->write($domain, $locale, $overrides);
    }

    public function revert(string $domain, string $locale, string $key): void
    {
        $overrides = $this->overrides($domain, $locale);
        unset($overrides[$key]);
        $this->write($domain, $locale, $overrides);
    }

    public function add(string $locale): void
    {
        $this->overrides->disable($locale, false);
        $this->write('messages', $locale, $this->overrides('messages', $locale));
    }

    public function remove(string $locale): void
    {
        foreach ($this->overrides->files($locale) as $file) {
            unlink($file);
        }

        if (glob($this->directory() . '/*.' . $locale . '.yaml')) {
            $this->overrides->disable($locale, true);
        }

        $this->unlock($locale);
    }

    public function lock(string $locale, User $user): bool
    {
        $holder = $this->holder($locale);

        if ($holder !== null && $holder['user'] !== $user->getId()) {
            return false;
        }

        $locks = (array) $this->settings->get(self::LOCKS);
        $locks[$locale] = ['user' => $user->getId(), 'name' => $user->getUsername(), 'until' => time() + self::LOCK_MINUTES * 60];
        $this->settings->set(self::LOCKS, $locks);

        return true;
    }

    public function unlock(string $locale): void
    {
        $locks = (array) $this->settings->get(self::LOCKS);
        unset($locks[$locale]);
        $this->settings->set(self::LOCKS, $locks);
    }

    public function holder(string $locale): ?array
    {
        $lock = ((array) $this->settings->get(self::LOCKS))[$locale] ?? null;

        return is_array($lock) && $lock['until'] > time() ? $lock : null;
    }

    protected function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $flat += is_array($value) ? $this->flatten($value, $path) : [$path => (string) $value];
        }

        return $flat;
    }

    protected function dump(array $tree, int $depth = 0): string
    {
        $yaml = '';

        foreach ($tree as $key => $value) {
            $yaml .= str_repeat('    ', $depth) . (preg_match('/^[A-Za-z0-9_-]+$/', (string) $key) ? $key : json_encode((string) $key, JSON_UNESCAPED_UNICODE)) . ':';
            $yaml .= is_array($value) ? "\n" . $this->dump($value, $depth + 1) : ' ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        return $yaml;
    }

    protected function file(string $domain, string $locale): string
    {
        return $this->directory() . '/' . $domain . '.' . $locale . '.yaml';
    }

    protected function directory(): string
    {
        return $this->overrides->base();
    }

    protected function parse(string $file): array
    {
        return is_file($file) ? $this->flatten((array) $this->yaml->parseFile($file)) : [];
    }

    protected function write(string $domain, string $locale, array $messages): void
    {
        $file = $this->overrides->file($domain, $locale);
        $this->overrides->prepare();

        if ($messages === [] && is_file($this->file($domain, $locale))) {
            is_file($file) && unlink($file);

            return;
        }

        ksort($messages);
        file_put_contents($file, $messages === [] ? "{}\n" : $this->dump($messages));
    }
}
