<?php

namespace App\Shared\Badge;

class BadgeDesign
{
    public const SHAPES = ['hexagon', 'shield', 'circle', 'diamond', 'octagon'];
    public const RARITIES = ['common', 'rare', 'epic', 'legendary'];
    public const FRAMES = ['auto', 'simple', 'double'];
    public const SECRET = 'M9 9a3 3 0 1 1 4 2.8c-.6.3-1 .9-1 1.6V15M12 18h.01';
    public const ICONS = [
        'bolt' => 'M13 2 3 14h9l-1 8 10-12h-9l1-8z',
        'keys' => 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M7 14h10',
        'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'eye' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
        'trophy' => 'M8 4h8v5a4 4 0 0 1-8 0V4zM8 6H4a3 3 0 0 0 4 4M16 6h4a3 3 0 0 1-4 4M12 13v4M8 21h8',
        'star' => 'M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.5 6.7 19.4l1.2-6L3.4 9.3l6-.7L12 3z',
        'flame' => 'M12 3c1 4 5 5 5 10a5 5 0 0 1-10 0c0-3 2-4 2-7 1 1 2 2 3 4 0-3 0-5 0-7z',
        'users' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 3a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3',
        'crown' => 'M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8z',
        'clock' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4',
        'play' => 'M7 4l13 8-13 8V4z',
        'heart' => 'M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z',
        'target' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z',
        'medal' => 'M8 3h8l-2 6h-4L8 3zM12 9a6 6 0 1 0 0 12 6 6 0 0 0 0-12z',
        'brain' => 'M9 4a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 6 1V5a3 3 0 0 0-3-1zM15 4a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-6 1',
    ];
    private const GLOW = [
        'common' => 0,
        'rare' => 0.5,
        'epic' => 0.8,
        'legendary' => 1.2,
    ];

    public static function normalize(array $design, ?string $color = null): array
    {
        $rarity = in_array($design['rarity'] ?? '', self::RARITIES, true) ? $design['rarity'] : 'common';
        $frame = in_array($design['frame'] ?? '', self::FRAMES, true) ? $design['frame'] : 'auto';

        return [
            'shape' => in_array($design['shape'] ?? '', self::SHAPES, true) ? $design['shape'] : 'hexagon',
            'rarity' => $rarity,
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($design['color'] ?? '')) ? $design['color'] : $color,
            'icon' => array_key_exists($design['icon'] ?? '', self::ICONS) ? $design['icon'] : 'star',
            'glow' => max(0, min(1.5, (float) ($design['glow'] ?? self::GLOW[$rarity]))),
            'label' => mb_substr((string) ($design['label'] ?? ''), 0, 6),
            'double' => $frame === 'double' || ($frame === 'auto' && in_array($rarity, ['epic', 'legendary'], true)),
        ];
    }

    public static function path(array $design, bool $secret): string
    {
        return $secret ? self::SECRET : self::ICONS[$design['icon']];
    }
}
