<?php

namespace App\Admin\Reset;

use App\Repository\UserRepository;
use DateTime;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use NeoPHP\Component\Container\Attribute\Autowire;
use RuntimeException;

class BackupManager
{
    public const DIRECTORY = 'var/backups';
    public const PATTERN = '/^battlegame-(backup-)?[0-9]{4}-[0-9]{2}-[0-9]{2}(-[0-9]{6})?(-[a-z0-9-]{1,40})?\.(sql|zip)$/';
    public const FILES = ['public/uploads', 'var/games'];
    public const SKIP = ['.git', 'node_modules'];
    private const CHUNK = 500;

    public function __construct(
        protected UserRepository $users,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function directory(): string
    {
        $directory = $this->rootPath . '/' . self::DIRECTORY;

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    public function list(): array
    {
        $files = [];

        foreach (glob($this->directory() . '/battlegame-*') ?: [] as $path) {
            $name = basename($path);

            if (preg_match(self::PATTERN, $name)) {
                $files[] = ['name' => $name, 'size' => filesize($path), 'date' => (new DateTime())->setTimestamp(filemtime($path)), 'type' => str_ends_with($name, '.zip') ? 'files' : 'database', 'auto' => str_contains($name, '-auto')];
            }
        }

        usort($files, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $files;
    }

    public function path(string $name): string
    {
        $path = $this->directory() . '/' . basename($name);

        if (!preg_match(self::PATTERN, basename($name)) || !is_file($path)) {
            throw new RuntimeException('Backup not found');
        }

        return $path;
    }

    public function create(string $label = 'manuelle'): string
    {
        $label = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');
        $name = 'battlegame-' . date('Y-m-d-His') . ($label !== '' ? '-' . substr($label, 0, 40) : '') . '.sql';
        $connection = $this->connection();
        $handle = fopen($this->directory() . '/' . $name, 'wb');
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");

        foreach ($connection->fetchFirstColumn('SHOW TABLES') as $table) {
            $create = $connection->fetchAssociative('SHOW CREATE TABLE `' . $table . '`');
            fwrite($handle, "\nDROP TABLE IF EXISTS `" . $table . "`;\n" . ($create['Create Table'] ?? array_values($create)[1]) . ";\n");
            $offset = 0;

            do {
                $rows = $connection->fetchAllAssociative(sprintf('SELECT * FROM `%s` LIMIT %d OFFSET %d', $table, self::CHUNK, $offset));

                if ($rows !== []) {
                    $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                    $values = array_map(fn (array $row): string => '(' . implode(', ', array_map($this->quote(...), $row)) . ')', $rows);
                    fwrite($handle, 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES ' . implode(', ', $values) . ";\n");
                }

                $offset += self::CHUNK;
            } while (count($rows) === self::CHUNK);
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return $name;
    }

    public function createFiles(string $label = 'fichiers'): string
    {
        $label = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');
        $name = 'battlegame-' . date('Y-m-d-His') . '-' . substr($label, 0, 40) . '.zip';
        $zip = new ZipArchive();
        $zip->open($this->directory() . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (self::FILES as $folder) {
            $directory = $this->rootPath . '/' . $folder;

            if (!is_dir($directory)) {
                continue;
            }

            $base = strlen(realpath($directory)) + 1;

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = str_replace('\\', '/', substr($file->getRealPath(), $base));

                if ($file->isFile() && array_intersect(explode('/', $relative), self::SKIP) === []) {
                    $zip->addFile($file->getRealPath(), $folder . '/' . $relative);
                }
            }
        }

        $zip->addFromString('README.txt', "Sauvegarde des fichiers BattleGame du " . date('d/m/Y H:i') . ".\nDécompresser à la racine du projet pour restaurer : " . implode(', ', self::FILES) . ".\n");
        $zip->close();

        return $name;
    }

    public function prune(int $keep): int
    {
        $removed = 0;

        foreach (['database', 'files'] as $type) {
            $auto = array_values(array_filter($this->list(), fn (array $file): bool => $file['auto'] && $file['type'] === $type));

            foreach (array_slice($auto, max(1, $keep)) as $file) {
                $this->delete($file['name']);
                $removed++;
            }
        }

        return $removed;
    }

    public function restore(string $name): int
    {
        if (!str_ends_with($name, '.sql')) {
            throw new RuntimeException('Only SQL backups can be restored');
        }

        $connection = $this->connection();
        $statements = 0;
        $buffer = '';
        $handle = fopen($this->path($name), 'rb');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            while (($line = fgets($handle)) !== false) {
                $buffer .= $line;

                if (str_ends_with(rtrim($line, "\r\n"), ';')) {
                    $sql = trim($buffer);
                    $buffer = '';

                    if ($sql !== '' && !str_starts_with($sql, 'SET FOREIGN_KEY_CHECKS')) {
                        $connection->executeStatement(rtrim($sql, ';'));
                        $statements++;
                    }
                }
            }
        } finally {
            fclose($handle);
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $statements;
    }

    public function delete(string $name): void
    {
        unlink($this->path($name));
    }

    protected function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . strtr((string) $value, ['\\' => '\\\\', "'" => "\\'", "\n" => '\\n', "\r" => '\\r', "\0" => '\\0', "\x1a" => '\\Z']) . "'";
    }

    protected function connection(): mixed
    {
        return $this->users->getOrm()->getConnection();
    }
}
