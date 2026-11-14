<?php

namespace App\Shared\Shop;

use App\Entity\ShopProduct;
use App\Shared\Badge\BadgeDesign;

class CosmeticDesign
{
    public const PAINTS = ['solid', 'gradient', 'conic'];
    public const ANIMATIONS = ['none', 'spin', 'pulse', 'shimmer'];
    public const PATTERNS = ['none', 'dots', 'stripes', 'grid', 'waves'];
    public const EFFECTS = ['rain', 'burst', 'fountain'];
    private const LEGACY_VICTORY = [
        'confetti' => ['icon' => 'star', 'color' => '#FFC94A', 'shape' => 'circle'],
        'neon' => ['icon' => 'bolt', 'color' => '#2EE6D6', 'shape' => 'diamond'],
        'gold' => ['icon' => 'trophy', 'color' => '#FFC94A', 'shape' => 'shield'],
        'hearts' => ['icon' => 'heart', 'color' => '#FF5470', 'shape' => 'circle'],
    ];

    public static function frame(array $data): array
    {
        return [
            'colors' => self::colors($data['colors'] ?? [], ['#2EE6D6', '#7C5CFF', '#FF3DCB']),
            'paint' => in_array($data['paint'] ?? '', self::PAINTS, true) ? $data['paint'] : 'gradient',
            'animation' => in_array($data['animation'] ?? '', self::ANIMATIONS, true) ? $data['animation'] : 'none',
            'width' => max(2, min(6, (int) ($data['width'] ?? 3))),
            'glow' => max(0, min(28, (int) ($data['glow'] ?? 12))),
        ];
    }

    public static function banner(array $data): array
    {
        return [
            'mode' => ($data['mode'] ?? '') === 'image' ? 'image' : 'gradient',
            'colors' => self::colors($data['colors'] ?? [], ['#7C5CFF', '#FF3DCB', '#FFC94A']),
            'angle' => max(0, min(360, (int) ($data['angle'] ?? 120))),
            'pattern' => in_array($data['pattern'] ?? '', self::PATTERNS, true) ? $data['pattern'] : 'none',
        ];
    }

    public static function victory(array $data): array
    {
        $design = BadgeDesign::normalize([
            'shape' => $data['shape'] ?? 'circle',
            'rarity' => $data['rarity'] ?? 'rare',
            'icon' => $data['icon'] ?? 'star',
            'color' => $data['color'] ?? '#FFC94A',
            'frame' => 'simple',
        ]);

        return $design + [
            'animation' => in_array($data['animation'] ?? '', self::EFFECTS, true) ? $data['animation'] : 'rain',
            'count' => max(8, min(60, (int) ($data['count'] ?? 30))),
        ];
    }

    public static function look(ShopProduct $product): array
    {
        $kind = (string) $product->option('kind');
        $value = $product->option('value');
        $design = (array) $product->option('design', []);

        return match ($kind) {
            'frame' => $design !== [] ? self::frameLook(self::frame($design)) : ['frame' => (string) $value],
            'banner' => $design !== [] ? self::bannerLook(self::banner($design), $product->getImage()) : ['banner' => (string) $value],
            'victory' => ['victory' => self::victoryLook($design !== [] ? self::victory($design) : self::victory(self::LEGACY_VICTORY[(string) $value] ?? []))],
            'color', 'title' => [$kind => (string) $value],
            default => [],
        };
    }

    public static function frameLook(array $design): array
    {
        [$a, $b, $c] = $design['colors'];

        return [
            'frame' => sprintf('custom avatar--frame-paint-%s avatar--frame-anim-%s', $design['paint'], $design['animation']),
            'frame_style' => sprintf('--frame-a:%s;--frame-b:%s;--frame-c:%s;--frame-width:%dpx;--frame-glow:%dpx', $a, $b, $c, $design['width'], $design['glow']),
        ];
    }

    public static function bannerLook(array $design, ?string $image): array
    {
        [$a, $b, $c] = $design['colors'];
        $style = sprintf('--banner-a:%s;--banner-b:%s;--banner-c:%s;--banner-angle:%ddeg', $a, $b, $c, $design['angle']);

        if ($design['mode'] === 'image' && $image !== null && preg_match('#^[A-Za-z0-9/_.\-]+$#', $image)) {
            $style .= sprintf(";--banner-image:url('%s')", $image);
        }

        return ['banner' => 'custom profile-banner--pattern-' . $design['pattern'], 'banner_style' => $style];
    }

    public static function victoryLook(array $design): array
    {
        return $design + ['path' => BadgeDesign::ICONS[$design['icon']] ?? BadgeDesign::ICONS['star']];
    }

    protected static function colors(mixed $colors, array $defaults): array
    {
        $list = array_values((array) $colors);

        return array_map(fn (int $index): string => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($list[$index] ?? '')) ? strtoupper($list[$index]) : $defaults[$index], [0, 1, 2]);
    }
}
