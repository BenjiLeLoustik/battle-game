<?php

namespace App\Shared\Game;

class GameAutoloader
{
    private const NAMESPACE = 'BattleGame\\';
    protected ?array $prefixes = null;

    public function __construct(
        protected string $directory,
    ) {
    }

    public function register(): void
    {
        spl_autoload_register($this->load(...));
    }

    public function load(string $class): void
    {
        if (!str_starts_with($class, self::NAMESPACE)) {
            return;
        }

        foreach ($this->prefixes() as $prefix => $path) {
            $file = $path . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (str_starts_with($class, $prefix) && is_file($file)) {
                require $file;

                return;
            }
        }
    }

    protected function prefixes(): array
    {
        if ($this->prefixes !== null) {
            return $this->prefixes;
        }

        $this->prefixes = [];

        foreach (glob($this->directory . '/*/composer.json') ?: [] as $file) {
            $composer = json_decode((string) file_get_contents($file), true);

            foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $path) {
                $this->prefixes[$prefix] = dirname($file) . '/' . trim($path, '/');
            }
        }

        return $this->prefixes;
    }
}