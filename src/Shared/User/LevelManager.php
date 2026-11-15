<?php

namespace App\Shared\User;

use App\Repository\LevelDefinitionRepository;
use App\Shared\Content\ContentTranslationManager;

class LevelManager
{
    public function __construct(
        protected LevelDefinitionRepository $levels,
        protected ContentTranslationManager $contents,
    ) {
    }

    public function level(int $experience): int
    {
        $experience = max(0, $experience);
        $levels = $this->definitions();

        if ($levels === []) {
            return 1 + (int) floor(sqrt($experience / 20));
        }

        $last = $levels[count($levels) - 1];

        if ($experience >= $last->getRequiredExperience()) {
            $step = $this->step($levels);

            return $last->getNumber()
                + intdiv($experience - $last->getRequiredExperience(), $step);
        }

        foreach ($levels as $definition) {
            if ($experience < $definition->getRequiredExperience()) {
                return max(1, $definition->getNumber() - 1);
            }
        }

        return $last->getNumber();
    }

    public function change(int $before, int $gained): array
    {
        $progress = $this->progress($before + $gained);

        return [
            'before' => $this->level($before),
            'after' => $progress['level'],
            'percent' => $progress['percent'],
        ];
    }

    public function threshold(int $level): int
    {
        $level = max(1, $level);
        $definitions = $this->definitions();

        if ($definitions === []) {
            return 20 * ($level - 1) ** 2;
        }

        foreach ($definitions as $definition) {
            if ($definition->getNumber() === $level) {
                return $definition->getRequiredExperience();
            }
        }

        $last = $definitions[count($definitions) - 1];

        if ($level < $last->getNumber()) {
            return $last->getRequiredExperience();
        }

        return $last->getRequiredExperience()
            + ($level - $last->getNumber()) * $this->step($definitions);
    }

    public function progress(int $experience): array
    {
        $experience = max(0, $experience);
        $level = $this->level($experience);
        $floor = $this->threshold($level);
        $next = $this->threshold($level + 1);

        return [
            'level' => $level,
            'experience' => $experience,
            'current' => $experience - $floor,
            'needed' => $next - $floor,
            'next' => $next,
            'percent' => (int) floor(
                ($experience - $floor) / max(1, $next - $floor) * 100,
            ),
        ];
    }

    public function titles(int $level): array
    {
        $titles = [];

        foreach ($this->definitions() as $definition) {
            $name = $this->contents->localize($definition, 'name');

            if ($name === '') {
                continue;
            }

            $titles[] = [
                'name' => $name,
                'level' => $definition->getNumber(),
                'unlocked' => $level >= $definition->getNumber(),
                'badge' => $definition->getBadge(),
            ];
        }

        return $titles;
    }

    public function nextTitle(int $level): ?array
    {
        foreach ($this->titles($level) as $title) {
            if (!$title['unlocked']) {
                return $title;
            }
        }

        return null;
    }

    protected function definitions(): array
    {
        return $this->levels->findAll(['number' => 'ASC']);
    }

    protected function step(array $definitions): int
    {
        if (count($definitions) < 2) {
            return 20;
        }

        $last = $definitions[count($definitions) - 1];
        $previous = $definitions[count($definitions) - 2];

        return max(1, $last->getRequiredExperience() - $previous->getRequiredExperience());
    }
}