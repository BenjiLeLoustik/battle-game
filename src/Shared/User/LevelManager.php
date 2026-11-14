<?php

namespace App\Shared\User;

class LevelManager
{
    public const TITLES = [5 => 'recruit', 15 => 'regular', 25 => 'veteran', 40 => 'legend'];
    private const EXPERIENCE_STEP = 20;

    public function level(int $experience): int
    {
        return 1 + (int) floor(sqrt(max(0, $experience) / self::EXPERIENCE_STEP));
    }

    public function change(int $before, int $gained): array
    {
        $progress = $this->progress($before + $gained);

        return ['before' => $this->level($before), 'after' => $progress['level'], 'percent' => $progress['percent']];
    }

    public function titles(int $level): array
    {
        return array_map(
            fn (string $key, int $required): array => ['key' => $key, 'level' => $required, 'unlocked' => $level >= $required],
            self::TITLES,
            array_keys(self::TITLES),
        );
    }

    public function nextTitle(int $level): ?array
    {
        return current(array_filter($this->titles($level), fn (array $title): bool => !$title['unlocked'])) ?: null;
    }

    public function threshold(int $level): int
    {
        return self::EXPERIENCE_STEP * ($level - 1) ** 2;
    }

    public function progress(int $experience): array
    {
        $level = $this->level($experience);
        $floor = $this->threshold($level);
        $next = $this->threshold($level + 1);

        return [
            'level' => $level,
            'experience' => $experience,
            'current' => $experience - $floor,
            'needed' => $next - $floor,
            'next' => $next,
            'percent' => (int) floor(($experience - $floor) / max(1, $next - $floor) * 100),
        ];
    }
}
