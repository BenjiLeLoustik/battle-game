<?php

namespace App\Shared\Server;

use App\Shared\Server\Exception\MercureChecksumException;
use App\Shared\Server\Exception\MercureDownloadException;
use NeoPHP\Component\Container\Attribute\Autowire;
use PharData;

class MercureHubManager
{
    public const VERSION = '1.1.6';
    private const RELEASE_URL = 'https://github.com/dunglas/mercure/releases/download/v%s/%s';

    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        private string $rootPath,
    ) {
    }

    public function getBinary(): string
    {
        return sprintf(
            '%s/var/mercure/mercure%s',
            $this->rootPath,
            PHP_OS_FAMILY === 'Windows' ? '.exe' : '',
        );
    }

    public function getConfig(): string
    {
        return sprintf('%s/config/mercure/Caddyfile', $this->rootPath);
    }

    public function isInstalled(): bool
    {
        return is_file($this->getBinary());
    }

    public function install(): void
    {
        $archive = $this->getArchiveName();
        $content = $this->download($archive);

        if (!preg_match('/^' . hash('sha256', $content) . '\s+\*?' . preg_quote($archive, '/') . '$/m', $this->download('checksums.txt'))) {
            throw new MercureChecksumException([
                'archive' => $archive,
            ]);
        }

        $directory = dirname($this->getBinary());
        $file = $directory . '/' . $archive;

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($file, $content);
        (new PharData($file))->extractTo($directory, basename($this->getBinary()), true);
        unlink($file);
    }

    private function download(string $file): string
    {
        $content = @file_get_contents(sprintf(self::RELEASE_URL, self::VERSION, $file));

        if ($content === false) {
            throw new MercureDownloadException([
                'file' => $file,
            ]);
        }

        return $content;
    }

    private function getArchiveName(): string
    {
        $os = in_array(PHP_OS_FAMILY, ['Windows', 'Darwin'], true) ? PHP_OS_FAMILY : 'Linux';
        $arch = in_array(strtolower(php_uname('m')), ['arm64', 'aarch64'], true) ? 'arm64' : 'x86_64';

        return sprintf(
            'mercure_%s_%s.%s',
            $os,
            $arch,
            $os === 'Windows' ? 'zip' : 'tar.gz'
        );
    }
}