<?php

namespace App\Admin\Studio;

use App\Admin\Studio\Exception\StudioFileException;
use App\Entity\Game;
use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameManager;
use FilesystemIterator;
use NeoPHP\Component\Container\Attribute\Autowire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class EditorManager
{
    private const EXTENSIONS = ['php', 'js', 'json', 'css', 'md', 'yaml', 'yml', 'sql', 'txt', 'html', 'svg'];
    private const PROTECTED = ['game.json', 'composer.json'];
    private const MAX_SIZE = 524288;

    public function __construct(
        protected GameManager $games,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function tree(Game $game): array
    {
        $root = $this->root($game);
        $entries = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $entries[] = [
                'path' => $path,
                'name' => $file->getFilename(),
                'depth' => substr_count($path, '/'),
                'folder' => $file->isDir(),
                'editable' => $file->isFile() && $this->isEditable($path),
                'protected' => in_array($path, self::PROTECTED, true),
            ];
        }

        usort($entries, $this->compare(...));

        return $entries;
    }

    public function read(Game $game, string $path): string
    {
        $file = $this->resolve($game, $path);

        if (!is_file($file)) {
            throw new StudioFileException(['path' => $path]);
        }

        return (string) file_get_contents($file);
    }

    public function write(Game $game, string $path, string $content): void
    {
        $file = $this->resolve($game, $path);

        if (strlen($content) > self::MAX_SIZE) {
            throw new StudioFileException(['path' => $path]);
        }

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $content);
        $this->sync($game, $path);
    }

    public function create(Game $game, string $path): void
    {
        if (file_exists($this->resolve($game, $path))) {
            throw new StudioFileException(['path' => $path]);
        }

        $this->write($game, $path, '');
    }

    public function createFolder(Game $game, string $path): void
    {
        $folder = $this->resolve($game, $path, false);

        if (file_exists($folder)) {
            throw new StudioFileException(['path' => $path]);
        }

        mkdir($folder, 0777, true);
    }

    public function delete(Game $game, string $path): void
    {
        $target = $this->resolve($game, $path, false);

        if ($this->isProtected($path) || !file_exists($target)) {
            throw new StudioFileException(['path' => $path]);
        }

        if (is_dir($target)) {
            $this->removeDirectory($target);
        } else {
            unlink($target);
        }

        $this->sync($game, $path);
    }

    public function rename(Game $game, string $from, string $to): void
    {
        $source = $this->resolve($game, $from, false);
        $target = $this->resolve($game, $to, false);

        if ($this->isProtected($from) || $this->isProtected($to) || !file_exists($source) || file_exists($target)) {
            throw new StudioFileException(['path' => $to]);
        }

        if (is_file($source) && $this->isEditable($from) && !$this->isEditable($to)) {
            throw new StudioFileException(['path' => $to]);
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        rename($source, $target);
        $this->sync($game, $from);
        $this->sync($game, $to);
    }

    public function validate(Game $game): ?string
    {
        try {
            $this->games->manifest($game->getDirectory());

            return null;
        } catch (GameManifestException $exception) {
            return $exception->getContext()['reason'];
        } catch (Throwable $exception) {
            return $exception->getMessage() . ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')';
        }
    }

    public function publish(Game $game): Game
    {
        return $this->games->install($game->getDirectory());
    }

    protected function compare(array $first, array $second): int
    {
        $left = explode('/', $first['path']);
        $right = explode('/', $second['path']);

        foreach ($left as $index => $part) {
            if (!isset($right[$index])) {
                return 1;
            }

            if ($part !== $right[$index]) {
                $leftFolder = $index < count($left) - 1 || $first['folder'];
                $rightFolder = $index < count($right) - 1 || $second['folder'];

                return $leftFolder === $rightFolder ? strnatcasecmp($part, $right[$index]) : ($leftFolder ? -1 : 1);
            }
        }

        return count($left) <=> count($right);
    }

    protected function sync(Game $game, string $path): void
    {
        $path = $this->normalize($path);

        if (str_starts_with($path . '/', 'assets/')) {
            $this->games->removeAssets($game->getSlug());
            $this->games->publishAssets($game->getDirectory(), $game->getSlug());
        }

        if ($path === 'game.json' && $this->validate($game) === null) {
            $this->publish($game);
        }
    }

    protected function resolve(Game $game, string $path, bool $editable = true): string
    {
        $path = $this->normalize($path);

        if (preg_match('#^[A-Za-z0-9_\-./]+$#', $path) !== 1 || str_contains($path, '..') || ($editable && !$this->isEditable($path))) {
            throw new StudioFileException(['path' => $path]);
        }

        return $this->root($game) . '/' . $path;
    }

    protected function removeDirectory(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    protected function normalize(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    protected function isProtected(string $path): bool
    {
        return in_array($this->normalize($path), self::PROTECTED, true);
    }

    protected function isEditable(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    protected function root(Game $game): string
    {
        return $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory());
    }
}