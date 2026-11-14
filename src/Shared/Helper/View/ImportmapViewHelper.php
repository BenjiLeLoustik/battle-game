<?php

namespace App\Shared\Helper\View;

use NeoPHP\Component\Asset\AssetManagerInterface;
use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Component\View\Contract\ViewSafeHtmlInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ImportmapViewHelper implements ViewFunctionInterface, ViewSafeHtmlInterface
{
    public function __construct(
        private AssetManagerInterface $assets,
    ) {
    }

    public function getName(): string
    {
        return 'importmap';
    }

    public function __invoke(): string
    {
        $directory = sprintf('%s/js', $this->assets->getSourcePath());
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $imports = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $imports[substr($path, 0, -3)] = $this->assets->url('js/' . $path);
        }

        return sprintf(
            '<script type="importmap">%s</script>',
            json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES)
        );
    }
}