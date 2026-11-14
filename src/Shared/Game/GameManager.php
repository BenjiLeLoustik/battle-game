<?php

namespace App\Shared\Game;

use App\Entity\Game;
use App\Repository\CategoryRepository;
use App\Repository\GameRepository;
use App\Sdk\AbstractSdk;
use App\Sdk\AbstractSdkServer;
use App\Shared\Badge\BadgeManager;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Game\Exception\GameManifestException;
use FilesystemIterator;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Package\Yaml\YamlManagerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GameManager
{
    public const GAMES_DIRECTORY = 'var/games';
    public const PUBLIC_DIRECTORY = 'public/games';
    private const REQUIRED_KEYS = ['slug', 'name', 'version', 'category', 'players', 'manager', 'server', 'client'];
    private const AGGREGATES = ['min', 'max', 'avg', 'sum'];

    public function __construct(
        protected GameRepository $games,
        protected CategoryRepository $categories,
        protected ContainerManagerInterface $container,
        protected BadgeManager $badges,
        protected YamlManagerInterface $yaml,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function install(string $directory, GameStatusEnum $status = GameStatusEnum::LIVE): Game
    {
        $manifest = $this->manifest($directory);
        $game = $this->games->findOneBy(['slug' => $manifest['slug']]) ?? (new Game())->setStatus($status);
        $game->setManifest($manifest)->setDirectory($directory)->setCategory($this->categories->findBySlug($manifest['category']));

        $this->publishAssets($directory, $manifest['slug']);
        $this->games->save($game, true);
        $this->badges->syncGame($game);

        return $game;
    }

    public function sdk(Game $game): AbstractSdk
    {
        $sdk = $this->container->get($game->getManifest()['manager'])->setManifest($game->getManifest());
        $contents = [];

        foreach ($sdk->getLanguages() as $locale => $file) {
            $path = $this->path($game->getDirectory()) . '/' . $file;
            $contents[$locale] = is_file($path) ? (array) $this->yaml->parseFile($path) : [];
        }

        return $sdk->setContents($contents);
    }

    public function languages(Game $game): array
    {
        return array_map(fn (mixed $texts): array => $this->flatten((array) $texts), $this->sdk($game)->getContent('texts'));
    }

    public function server(Game $game): AbstractSdkServer
    {
        return $this->container->get($game->getManifest()['server']);
    }

    public function manifest(string $directory): array
    {
        $path = $this->path($directory);
        $manifest = is_file($path . '/game.json') ? json_decode((string) file_get_contents($path . '/game.json'), true) : null;

        if (!is_array($manifest)) {
            $this->fail('game.json is missing or is not valid JSON in ' . $path);
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (empty($manifest[$key])) {
                $this->fail('the key "' . $key . '" is missing in game.json');
            }
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $manifest['slug']) !== 1) {
            $this->fail('the slug must be in kebab-case');
        }

        if ($this->categories->findBySlug((string) $manifest['category']) === null) {
            $this->fail('the category "' . $manifest['category'] . '" does not exist');
        }

        if (($manifest['players']['min'] ?? 0) < 1 || ($manifest['players']['max'] ?? 0) < $manifest['players']['min']) {
            $this->fail('players.min and players.max are not valid');
        }

        if (!is_subclass_of($manifest['manager'], AbstractSdk::class)) {
            $this->fail('the manager must extend ' . AbstractSdk::class);
        }

        if (!is_subclass_of($manifest['server'], AbstractSdkServer::class)) {
            $this->fail('the server must extend ' . AbstractSdkServer::class);
        }

        if (!is_file($path . '/assets/' . $manifest['client'])) {
            $this->fail('the client file assets/' . $manifest['client'] . ' does not exist');
        }

        if (isset($manifest['points']) && (!is_array($manifest['points']) || !array_is_list($manifest['points']) || array_filter($manifest['points'], fn (mixed $value): bool => !is_int($value) || $value < 0) !== [])) {
            $this->fail('"points" must be a list of positive integers, e.g. [10, 5, 2]');
        }


        if (isset($manifest['solo']) && !is_bool($manifest['solo'])) {
            $this->fail('"solo" must be true or false');
        }

        foreach ($manifest['stats'] ?? [] as $stat) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $stat['key'] ?? '') !== 1 || !in_array($stat['aggregate'] ?? '', self::AGGREGATES, true)) {
                $this->fail('each stat needs a snake_case "key" and an "aggregate" (min, max, avg or sum)');
            }
        }

        return $manifest;
    }

    public function publishAssets(string $directory, string $slug): void
    {
        $source = $this->path($directory) . '/assets';
        $target = $this->rootPath . '/' . self::PUBLIC_DIRECTORY . '/' . $slug;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $destination = $target . substr($file->getPathname(), strlen($source));

            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0777, true);
            }

            copy($file->getPathname(), $destination);
        }
    }

    public function removeAssets(string $slug): void
    {
        $directory = $this->rootPath . '/' . self::PUBLIC_DIRECTORY . '/' . basename($slug);

        if (!is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory, FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    protected function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix . $key;
            $flat += is_array($value) ? $this->flatten($value, $path . '.') : [$path => (string) $value];
        }

        return $flat;
    }

    protected function path(string $directory): string
    {
        return $this->rootPath . '/' . self::GAMES_DIRECTORY . '/' . basename($directory);
    }

    protected function fail(string $reason): never
    {
        throw new GameManifestException(['reason' => $reason]);
    }
}