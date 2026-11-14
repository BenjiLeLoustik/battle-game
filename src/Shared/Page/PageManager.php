<?php

namespace App\Shared\Page;

use App\Entity\ContentTranslation;
use App\Entity\Page;
use App\Repository\ContentTranslationRepository;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Page\Enum\PageFormatEnum;
use NeoPHP\Component\Routing\Exception\RouteNotFoundException;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Markdown\MarkdownManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;
use Throwable;

class PageManager
{
    public const TOKEN = '/\{([a-z][a-z0-9_]{0,39})\}/';
    private const STRINGS = 'page_text';
    private const RESERVED = ['admin', 'builds', 'games', '_profiler', 'mercure', '.well-known'];

    public function __construct(
        protected MarkdownManagerInterface $markdown,
        protected RoutingManagerInterface $routing,
        protected ContentTranslationManager $contents,
        protected ContentTranslationRepository $translations,
        protected TranslationManagerInterface $translator,
    ) {
    }

    public static function normalize(string $path): string
    {
        $segments = array_map(
            fn (string $segment): string => trim((string) preg_replace('/[^a-z0-9]+/', '-', transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $segment)), '-'),
            explode('/', trim($path, " /\t\n"))
        );

        return implode('/', array_filter($segments, fn (string $segment): bool => $segment !== ''));
    }

    public function available(string $path): bool
    {
        if ($path === '' || in_array(explode('/', $path)[0], self::RESERVED, true)) {
            return false;
        }

        try {
            $this->routing->match('GET', '/' . $path);

            return false;
        } catch (RouteNotFoundException) {
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function keys(string $content): array
    {
        preg_match_all(self::TOKEN, $content, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function strings(Page $page): array
    {
        $strings = array_fill_keys($this->keys($page->getContent()), []);

        foreach ($this->stored($page) as $key => $locales) {
            if (isset($strings[$key])) {
                $strings[$key] = array_map(fn (ContentTranslation $translation): string => $translation->getValue(), $locales);
            }
        }

        return $strings;
    }

    public function saveStrings(Page $page, array $data): void
    {
        $stored = $this->stored($page);
        $locales = $this->contents->locales();

        foreach ($this->keys($page->getContent()) as $key) {
            foreach ($locales as $locale) {
                $value = trim((string) ($data[$key][$locale] ?? ''));
                $translation = $stored[$key][$locale] ?? null;
                unset($stored[$key][$locale]);

                if ($value !== '') {
                    $this->translations->save(($translation ?? (new ContentTranslation())
                        ->setType(self::STRINGS)
                        ->setObjectId($page->getId())
                        ->setLocale($locale)
                        ->setField($key))
                        ->setValue($value));
                } elseif ($translation !== null) {
                    $this->translations->delete($translation);
                }
            }
        }

        foreach ($stored as $locales) {
            foreach ($locales as $translation) {
                $this->translations->delete($translation);
            }
        }

        $this->translations->getOrm()->flush();
    }

    public function deleteStrings(Page $page): void
    {
        foreach ($this->translations->forObject(self::STRINGS, (int) $page->getId()) as $translation) {
            $this->translations->delete($translation);
        }

        $this->translations->getOrm()->flush();
    }

    public function html(Page $page): string
    {
        $strings = $this->strings($page);
        $locale = $this->translator->getLocale();
        $html = $page->getFormat() === PageFormatEnum::HTML;

        $content = preg_replace_callback(self::TOKEN, function (array $match) use ($strings, $locale, $html): string {
            $value = $strings[$match[1]][$locale] ?? $strings[$match[1]][ContentTranslationManager::REFERENCE] ?? $match[0];

            return $html ? htmlspecialchars($value) : $value;
        }, $page->getContent());

        return $html ? $content : $this->markdown->toHtml($content);
    }

    public function style(Page $page): string
    {
        $style = trim($page->getStyle());

        return $style === '' ? '' : '@scope (.custom-page__body) {' . str_ireplace('</style', '', $style) . '}';
    }

    protected function stored(Page $page): array
    {
        $stored = [];

        if ($page->getId() !== null) {
            foreach ($this->translations->forObject(self::STRINGS, $page->getId()) as $translation) {
                $stored[$translation->getField()][$translation->getLocale()] = $translation;
            }
        }

        return $stored;
    }
}
