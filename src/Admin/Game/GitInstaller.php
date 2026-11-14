<?php

namespace App\Admin\Game;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Shared\Game\Enum\GameStatusEnum;
use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameAutoloader;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GitInstaller
{
    private const INCOMING = '.incoming';
    private const URL_PATTERN = '#^https://[A-Za-z0-9.-]+/[A-Za-z0-9._/-]+?(\.git)?$#';
    private const REF_PATTERN = '/^[A-Za-z0-9._\/-]{1,80}$/';

    protected array $log = [];

    public function __construct(
        protected GameManager $games,
        protected GameRepository $repository,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function analyze(string $url, string $ref, string $token): array
    {
        $this->log = [];

        if (!preg_match(self::URL_PATTERN, $url) || !preg_match(self::REF_PATTERN, $ref)) {
            return $this->result(null, null, 'admin.games.invalid_url');
        }

        $id = bin2hex(random_bytes(6));
        $directory = self::INCOMING . '/' . $id;

        if (!$this->git(['clone', '--depth', '1', '--branch', $ref, $this->authenticated($url, $token), $this->path($directory)], $token)) {
            $this->remove($this->path($directory));

            return $this->result(null, null, 'admin.games.clone_failed');
        }

        (new GameAutoloader($this->path(self::INCOMING)))->register();

        try {
            $manifest = $this->games->manifest($directory);
        } catch (GameManifestException $exception) {
            $this->remove($this->path($directory));

            return $this->result(null, null, $exception->getMessage());
        }

        $this->log[] = 'game.json OK · ' . $manifest['slug'] . ' ' . $manifest['version'];

        return $this->result($id, $manifest, null);
    }

    public function install(string $id, string $url, string $ref, GameStatusEnum $status): ?Game
    {
        $incoming = $this->path(self::INCOMING . '/' . $id);

        if (!preg_match('/^[a-f0-9]{12}$/', $id) || !is_dir($incoming)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($incoming . '/game.json'), true);
        $directory = str_replace(' ', '', ucwords(str_replace('-', ' ', $manifest['slug'])));
        $existing = $this->repository->findOneBy(['slug' => $manifest['slug']]);

        if (is_dir($this->path($directory))) {
            $this->remove($this->path($directory));
        }

        rename($incoming, $this->path($directory));
        $game = $this->games->install($directory, $existing?->getStatus() ?? $status);
        $this->repository->save($game->setRepository($url, $ref), true);

        return $game;
    }

    public function update(Game $game): ?string
    {
        if ($game->getRepository() === null) {
            return 'admin.games.no_repository';
        }

        $path = $this->path($game->getDirectory());
        $ok = $this->git(['-C', $path, 'fetch', '--depth', '1', 'origin', (string) $game->getReference()], '')
            && $this->git(['-C', $path, 'reset', '--hard', 'FETCH_HEAD'], '');

        if (!$ok) {
            return 'admin.games.update_failed';
        }

        try {
            $this->games->install($game->getDirectory(), $game->getStatus());
        } catch (GameManifestException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function log(): array
    {
        return $this->log;
    }

    protected function git(array $arguments, string $token): bool
    {
        $command = 'git ' . implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1';
        $output = [];
        exec($command, $output, $code);
        $this->log[] = '$ ' . str_replace($token !== '' ? $token : "\0", '***', 'git ' . implode(' ', $arguments));

        foreach ($output as $line) {
            $this->log[] = $token !== '' ? str_replace($token, '***', $line) : $line;
        }

        return $code === 0;
    }

    protected function authenticated(string $url, string $token): string
    {
        return $token === '' ? $url : preg_replace('#^https://#', 'https://x-access-token:' . rawurlencode($token) . '@', $url);
    }

    protected function result(?string $id, ?array $manifest, ?string $error): array
    {
        return ['id' => $id, 'manifest' => $manifest, 'error' => $error, 'log' => $this->log];
    }

    protected function path(string $directory): string
    {
        return $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . $directory;
    }

    protected function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                chmod($item->getPathname(), 0666);
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
