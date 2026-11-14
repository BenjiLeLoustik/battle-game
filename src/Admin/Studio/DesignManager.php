<?php

namespace App\Admin\Studio;

use App\Entity\Game;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;

class DesignManager
{
    public const FILE = 'design.json';
    public const STYLE = 'assets/css/design.css';
    public const FONTS = [
        'Inter' => "Inter, sans-serif",
        'Chakra Petch' => "'Chakra Petch', sans-serif",
        'JetBrains Mono' => "'JetBrains Mono', monospace",
        'System' => 'system-ui, sans-serif',
    ];
    public const SHADOWS = [
        'none' => 'none',
        'soft' => '0 6px 18px rgba(0, 0, 0, 0.45)',
        'strong' => '0 14px 36px rgba(0, 0, 0, 0.6)',
        'glow-cyan' => '0 0 18px rgba(46, 230, 214, 0.55)',
        'glow-magenta' => '0 0 18px rgba(255, 61, 203, 0.55)',
        'glow-amber' => '0 0 18px rgba(255, 201, 74, 0.55)',
        'inset' => 'inset 0 4px 12px rgba(0, 0, 0, 0.6)',
    ];
    private const MAX_RULES = 200;
    private const COLOR = '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)|transparent)$/';
    private const LENGTH = '/^(-?\d{1,4}(\.\d{1,2})?(px|%|rem|em|vw|vh)?|auto)$/';
    private const GRADIENT = '/^linear-gradient\(\d{1,3}deg,\s*(#[0-9a-fA-F]{3,8}|transparent),\s*(#[0-9a-fA-F]{3,8}|transparent)\)$/';
    private const PROPERTIES = [
        'display' => ['block', 'flex', 'inline-flex', 'grid', 'none'],
        'flex-direction' => ['row', 'column', 'row-reverse', 'column-reverse'],
        'justify-content' => ['flex-start', 'center', 'flex-end', 'space-between', 'space-around'],
        'align-items' => ['flex-start', 'center', 'flex-end', 'stretch'],
        'gap' => self::LENGTH,
        'width' => self::LENGTH,
        'height' => self::LENGTH,
        'max-width' => self::LENGTH,
        'min-height' => self::LENGTH,
        'margin-top' => self::LENGTH,
        'margin-right' => self::LENGTH,
        'margin-bottom' => self::LENGTH,
        'margin-left' => self::LENGTH,
        'padding-top' => self::LENGTH,
        'padding-right' => self::LENGTH,
        'padding-bottom' => self::LENGTH,
        'padding-left' => self::LENGTH,
        'background-color' => self::COLOR,
        'background-image' => self::GRADIENT,
        'border-width' => self::LENGTH,
        'border-style' => ['none', 'solid', 'dashed', 'dotted'],
        'border-color' => self::COLOR,
        'border-radius' => self::LENGTH,
        'color' => self::COLOR,
        'font-family' => 'font',
        'font-size' => self::LENGTH,
        'font-weight' => ['300', '400', '500', '600', '700', '800'],
        'letter-spacing' => self::LENGTH,
        'text-align' => ['left', 'center', 'right'],
        'text-transform' => ['none', 'uppercase', 'lowercase'],
        'box-shadow' => 'shadow',
        'opacity' => '/^(0|1|0?\.\d{1,2})$/',
    ];

    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function properties(): array
    {
        return array_keys(self::PROPERTIES);
    }

    public function load(Game $game): array
    {
        $file = $this->root($game) . '/' . self::FILE;
        $rules = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return $this->clean(is_array($rules) ? $rules : []);
    }

    public function save(Game $game, array $rules): array
    {
        $rules = $this->clean($rules);
        $root = $this->root($game);

        if (!is_dir($root . '/assets/css')) {
            mkdir($root . '/assets/css', 0777, true);
        }

        file_put_contents($root . '/' . self::FILE, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        file_put_contents($root . '/' . self::STYLE, $this->css($rules));

        return $rules;
    }

    public function css(array $rules): string
    {
        $blocks = [];

        foreach ($rules as $selector => $properties) {
            $lines = array_map(fn (string $property, string $value): string => sprintf('    %s: %s !important;', $property, $this->output($property, $value)), array_keys($properties), $properties);
            $blocks[] = $selector . " {\n" . implode("\n", $lines) . "\n}\n";
        }

        return implode("\n", $blocks);
    }

    public function clean(array $rules): array
    {
        $clean = [];

        foreach (array_slice($rules, 0, self::MAX_RULES, true) as $selector => $properties) {
            if (!is_string($selector) || preg_match('/^\.[a-zA-Z][a-zA-Z0-9_-]{0,80}(\[data-[a-z-]{1,30}="[a-zA-Z0-9_-]{1,30}"\])?(:hover)?$/', $selector) !== 1 || !is_array($properties)) {
                continue;
            }

            foreach ($properties as $property => $value) {
                $value = trim((string) $value);

                if (isset(self::PROPERTIES[$property]) && $value !== '' && $this->valid($property, $value)) {
                    $clean[$selector][$property] = $value;
                }
            }
        }

        return $clean;
    }

    protected function valid(string $property, string $value): bool
    {
        $rule = self::PROPERTIES[$property];

        return match (true) {
            is_array($rule) => in_array($value, $rule, true),
            $rule === 'font' => isset(self::FONTS[$value]),
            $rule === 'shadow' => isset(self::SHADOWS[$value]),
            default => preg_match($rule, $value) === 1,
        };
    }

    protected function output(string $property, string $value): string
    {
        return match ($property) {
            'font-family' => self::FONTS[$value],
            'box-shadow' => self::SHADOWS[$value],
            default => $value,
        };
    }

    protected function root(Game $game): string
    {
        return $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory());
    }
}
