<?php

namespace App\Shared\Content;

use App\Entity\ContentTranslation;
use App\Entity\Game;
use App\Repository\ContentTranslationRepository;
use App\Shared\Game\GameManager;
use App\Shared\Translation\TranslationOverrides;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Package\Translation\TranslationManagerInterface;
use NeoPHP\Package\Yaml\YamlManagerInterface;

class ContentTranslationManager
{
    public const REFERENCE = TranslationOverrides::REFERENCE;

    private array $maps = [];
    private array $games = [];

    public function __construct(
        protected ContentTranslationRepository $translations,
        protected TranslationManagerInterface $translator,
        protected YamlManagerInterface $yaml,
        protected TranslationOverrides $overrides,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function locales(): array
    {
        return $this->overrides->locales();
    }

    public function available(): array
    {
        return array_values(array_intersect($this->locales(), $this->translator->getLocales()));
    }

    public function targets(): array
    {
        return array_values(array_diff($this->locales(), [self::REFERENCE]));
    }

    public function localize(object $entity, string $field, ?string $locale = null): mixed
    {
        $locale ??= $this->translator->getLocale();
        $source = $entity->{'get' . ucfirst($field)}();

        if ($locale === self::REFERENCE) {
            return $source;
        }

        $value = $entity instanceof Game
            ? $this->game($entity, $locale)[$field] ?? null
            : $this->map($this->type($entity), $locale)[$entity->getId()][$field] ?? null;

        return $value === null || $value === '' ? $source : $value;
    }

    public function values(TranslatableInterface $entity): array
    {
        $values = [];

        if ($entity->getId() !== null) {
            foreach ($this->translations->forObject($this->type($entity), $entity->getId()) as $translation) {
                $values[$translation->getLocale()][$translation->getField()] = $translation->getValue();
            }
        }

        return $values;
    }

    public function save(TranslatableInterface $entity, array $data): void
    {
        $existing = [];

        foreach ($this->translations->forObject($this->type($entity), $entity->getId()) as $translation) {
            $existing[$translation->getLocale()][$translation->getField()] = $translation;
        }

        foreach ($this->targets() as $locale) {
            foreach (array_keys($entity::getTranslatableFields()) as $field) {
                $value = trim((string) ($data[$locale][$field] ?? ''));
                $translation = $existing[$locale][$field] ?? null;

                if ($value === '' && $translation !== null) {
                    $this->translations->delete($translation);
                } elseif ($value !== '') {
                    $this->translations->save(($translation ?? (new ContentTranslation())
                        ->setType($this->type($entity))
                        ->setObjectId($entity->getId())
                        ->setLocale($locale)
                        ->setField($field))
                        ->setValue($value));
                }
            }
        }

        $this->translations->getOrm()->flush();
    }

    public function delete(TranslatableInterface $entity): void
    {
        foreach ($this->translations->forObject($this->type($entity), (int) $entity->getId()) as $translation) {
            $this->translations->delete($translation);
        }

        $this->translations->getOrm()->flush();
    }

    protected function type(object $entity): string
    {
        return strtolower(substr(strrchr($entity::class, '\\'), 1));
    }

    protected function map(string $type, string $locale): array
    {
        return $this->maps[$type][$locale] ??= $this->translations->map($type, $locale);
    }

    protected function game(Game $game, string $locale): array
    {
        $file = $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory()) . '/translations/game.' . $locale . '.yaml';

        return $this->games[$game->getDirectory()][$locale] ??= is_file($file) ? (array) $this->yaml->parseFile($file) : [];
    }
}
