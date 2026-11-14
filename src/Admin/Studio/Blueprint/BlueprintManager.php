<?php

namespace App\Admin\Studio\Blueprint;

use App\Entity\Game;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;

class BlueprintManager
{
    public const FILE = 'blueprint.json';
    public const MODES = ['simultaneous', 'turns'];
    public const ELEMENTS = ['text', 'counter', 'button', 'grid', 'input', 'timer', 'scores', 'message', 'choices', 'image', 'icon', 'divider', 'box', 'custom'];
    public const VARIANTS = ['normal', 'title', 'big', 'small', 'hint'];
    public const EVENTS = ['game_start', 'round_start', 'click', 'cell', 'answer', 'timeout'];
    public const OPERANDS = ['number', 'text', 'var', 'my', 'score', 'answer', 'cell', 'cellvalue', 'round', 'random', 'mark', 'players'];
    public const TARGETS = ['var', 'my', 'score'];
    public const OPS = ['==', '!=', '>', '<', '>=', '<=', 'contains'];
    public const CHECKS = ['compare', 'empty', 'line', 'full'];
    public const ACTIONS = ['set', 'add', 'random', 'set_cell', 'clear_board', 'message', 'done', 'next_turn', 'end_round', 'finish', 'sound'];
    public const SOUNDS = ['click', 'correct', 'wrong', 'gain', 'pop', 'place', 'capture', 'flip', 'roll', 'shoot', 'hit', 'goal', 'win', 'lose'];
    public const RESERVED = ['score', 'round', 'rounds', 'players', 'turn', 'phase'];
    public const EVENT_ELEMENTS = ['click' => ['button', 'custom'], 'cell' => ['grid'], 'answer' => ['input', 'choices', 'custom']];
    public const CODE_LIMIT = 6000;
    private const KEY = '/^[a-z][a-z0-9_]{0,20}$/';

    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function exists(Game $game): bool
    {
        return is_file($this->path($game));
    }

    public function load(Game $game): ?array
    {
        if (!$this->exists($game)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->path($game)), true);

        return $this->normalize(is_array($data) ? $data : []);
    }

    public function save(Game $game, array $blueprint): array
    {
        $blueprint = $this->normalize($blueprint);
        file_put_contents($this->path($game), json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return $blueprint;
    }

    public function normalize(array $data): array
    {
        $blueprint = [
            'version' => 1,
            'mode' => in_array($data['mode'] ?? null, self::MODES, true) ? $data['mode'] : 'simultaneous',
            'rounds' => $this->int($data['rounds'] ?? 5, 1, 50),
            'time' => $this->int($data['time'] ?? 20, 3, 600),
            'reveal' => $this->int($data['reveal'] ?? 2, 0, 15),
            'variables' => [],
            'elements' => [],
            'rules' => [],
        ];

        foreach (array_slice((array) ($data['variables'] ?? []), 0, 30) as $variable) {
            $key = (string) ($variable['key'] ?? '');

            if (!is_array($variable) || preg_match(self::KEY, $key) !== 1 || in_array($key, self::RESERVED, true) || isset($blueprint['variables'][$key])) {
                continue;
            }

            $type = in_array($variable['type'] ?? null, ['number', 'text', 'board'], true) ? $variable['type'] : 'number';
            $blueprint['variables'][$key] = [
                'key' => $key,
                'scope' => $type === 'board' ? 'game' : (($variable['scope'] ?? '') === 'player' ? 'player' : 'game'),
                'type' => $type,
                'initial' => mb_substr(trim((string) ($variable['initial'] ?? '')), 0, 80),
                'size' => $this->int($variable['size'] ?? 9, 4, 100),
                'reset' => (bool) ($variable['reset'] ?? false),
                'hidden' => (bool) ($variable['hidden'] ?? false),
            ];
        }

        $boxes = array_column(array_filter(array_slice((array) ($data['elements'] ?? []), 0, 40), fn (mixed $element): bool => is_array($element) && ($element['type'] ?? null) === 'box'), 'id');

        foreach (array_slice((array) ($data['elements'] ?? []), 0, 40) as $element) {
            $id = (string) ($element['id'] ?? '');
            $type = $element['type'] ?? null;

            if (!is_array($element) || preg_match('/^e\d{1,4}$/', $id) !== 1 || !in_array($type, self::ELEMENTS, true) || isset($blueprint['elements'][$id])) {
                continue;
            }

            $variable = (string) ($element['variable'] ?? '');
            $valid = match ($type) {
                'counter' => $variable === 'score' || (isset($blueprint['variables'][$variable]) && $blueprint['variables'][$variable]['type'] !== 'board'),
                'grid' => ($blueprint['variables'][$variable]['type'] ?? null) === 'board',
                default => true,
            };

            if (!$valid) {
                continue;
            }

            $parent = (string) ($element['parent'] ?? '');
            $src = trim((string) ($element['src'] ?? ''));
            $blueprint['elements'][$id] = [
                'id' => $id,
                'type' => $type,
                'text' => $type === 'icon' ? mb_substr(trim((string) ($element['text'] ?? '')), 0, 8) : $this->text($element['text'] ?? '', 300),
                'variable' => in_array($type, ['counter', 'grid'], true) ? $variable : '',
                'columns' => $this->int($element['columns'] ?? 3, 1, 12),
                'variant' => in_array($element['variant'] ?? null, self::VARIANTS, true) ? $element['variant'] : 'normal',
                'inline' => (bool) ($element['inline'] ?? false),
                'parent' => $type !== 'box' && in_array($parent, $boxes, true) ? $parent : '',
                'options' => $type === 'choices' ? array_slice(array_values(array_filter(array_map(fn (mixed $option): string => $this->text($option, 60), array_slice((array) ($element['options'] ?? []), 0, 40)), fn (string $option): bool => $option !== '')), 0, 8) : [],
                'src' => $type === 'image' && preg_match('#^(https://[^\s"\'<>]{4,300}|/[a-zA-Z0-9_./-]{1,200})$#', $src) === 1 ? $src : '',
                'html' => $type === 'custom' ? mb_substr((string) ($element['html'] ?? ''), 0, self::CODE_LIMIT) : '',
                'css' => $type === 'custom' ? str_ireplace('</style', '', mb_substr((string) ($element['css'] ?? ''), 0, self::CODE_LIMIT)) : '',
                'script' => $type === 'custom' ? mb_substr((string) ($element['script'] ?? ''), 0, self::CODE_LIMIT) : '',
            ];
        }

        foreach (array_slice((array) ($data['rules'] ?? []), 0, 60) as $rule) {
            $id = (string) ($rule['id'] ?? '');
            $when = $rule['when'] ?? null;

            if (!is_array($rule) || preg_match('/^r\d{1,4}$/', $id) !== 1 || !in_array($when, self::EVENTS, true) || isset($blueprint['rules'][$id])) {
                continue;
            }

            $element = (string) ($rule['element'] ?? '');

            if (isset(self::EVENT_ELEMENTS[$when]) && !in_array($blueprint['elements'][$element]['type'] ?? null, self::EVENT_ELEMENTS[$when], true)) {
                continue;
            }

            $conditions = array_values(array_filter(array_map(fn (mixed $condition): ?array => is_array($condition) ? $this->condition($condition, $blueprint, $when) : null, array_slice((array) ($rule['if'] ?? []), 0, 6))));
            $actions = array_values(array_filter(array_map(fn (mixed $action): ?array => is_array($action) ? $this->action($action, $blueprint, $when) : null, array_slice((array) ($rule['then'] ?? []), 0, 10))));

            if ($actions === []) {
                continue;
            }

            $blueprint['rules'][$id] = [
                'id' => $id,
                'when' => $when,
                'element' => isset(self::EVENT_ELEMENTS[$when]) ? $element : '',
                'if' => $conditions,
                'then' => $actions,
            ];
        }

        $blueprint['variables'] = array_values($blueprint['variables']);
        $blueprint['elements'] = array_values($blueprint['elements']);
        $blueprint['rules'] = array_values($blueprint['rules']);

        return $blueprint;
    }

    public function operand(mixed $operand, array $blueprint, string $when, ?array $kinds = null): ?array
    {
        if (!is_array($operand) || !in_array($operand['kind'] ?? null, $kinds ?? self::OPERANDS, true)) {
            return null;
        }

        $kind = $operand['kind'];
        $value = $operand['value'] ?? '';

        return match ($kind) {
            'number' => is_numeric($value) && abs((float) $value) <= 1000000 ? ['kind' => $kind, 'value' => (string) (0 + $value)] : null,
            'text' => ['kind' => $kind, 'value' => $this->text($value, 80)],
            'var' => $this->variable($blueprint, (string) $value, 'game', false) ? ['kind' => $kind, 'value' => (string) $value] : null,
            'my' => $this->variable($blueprint, (string) $value, 'player', false) ? ['kind' => $kind, 'value' => (string) $value] : null,
            'random' => ['kind' => $kind, 'min' => $this->int($operand['min'] ?? 1, -100000, 100000), 'max' => max($this->int($operand['min'] ?? 1, -100000, 100000), $this->int($operand['max'] ?? 10, -100000, 100000))],
            'answer' => $when === 'answer' ? ['kind' => $kind] : null,
            'cell', 'cellvalue' => $when === 'cell' ? ['kind' => $kind] : null,
            default => ['kind' => $kind],
        };
    }

    protected function condition(array $condition, array $blueprint, string $when): ?array
    {
        $check = in_array($condition['check'] ?? 'compare', self::CHECKS, true) ? ($condition['check'] ?? 'compare') : null;

        return match ($check) {
            'compare' => ($left = $this->operand($condition['left'] ?? null, $blueprint, $when)) && ($right = $this->operand($condition['right'] ?? null, $blueprint, $when)) && in_array($condition['op'] ?? null, self::OPS, true)
                ? ['check' => 'compare', 'left' => $left, 'op' => $condition['op'], 'right' => $right]
                : null,
            'empty' => $when === 'cell' ? ['check' => 'empty'] : null,
            'line' => $this->variable($blueprint, (string) ($condition['board'] ?? ''), 'game', true) ? ['check' => 'line', 'board' => $condition['board'], 'length' => $this->int($condition['length'] ?? 3, 2, 10)] : null,
            'full' => $this->variable($blueprint, (string) ($condition['board'] ?? ''), 'game', true) ? ['check' => 'full', 'board' => $condition['board']] : null,
            default => null,
        };
    }

    protected function action(array $action, array $blueprint, string $when): ?array
    {
        $do = $action['do'] ?? null;

        if (!in_array($do, self::ACTIONS, true)) {
            return null;
        }

        return match ($do) {
            'set', 'add' => ($target = $this->operand($action['target'] ?? null, $blueprint, $when, self::TARGETS)) && ($value = $this->operand($action['value'] ?? null, $blueprint, $when))
                ? ['do' => $do, 'target' => $target, 'value' => $value]
                : null,
            'random' => ($target = $this->operand($action['target'] ?? null, $blueprint, $when, self::TARGETS))
                ? ['do' => $do, 'target' => $target, 'min' => $this->int($action['min'] ?? 1, -100000, 100000), 'max' => max($this->int($action['min'] ?? 1, -100000, 100000), $this->int($action['max'] ?? 10, -100000, 100000))]
                : null,
            'set_cell' => $when === 'cell' && $this->variable($blueprint, (string) ($action['board'] ?? ''), 'game', true)
                ? ['do' => $do, 'board' => $action['board'], 'value' => $this->operand($action['value'] ?? null, $blueprint, $when) ?? ['kind' => 'mark']]
                : null,
            'clear_board' => $this->variable($blueprint, (string) ($action['board'] ?? ''), 'game', true) ? ['do' => $do, 'board' => $action['board']] : null,
            'message' => trim((string) ($action['text'] ?? '')) !== '' ? ['do' => $do, 'text' => $this->text($action['text'], 200), 'to' => ($action['to'] ?? '') === 'all' ? 'all' : 'me'] : null,
            'sound' => in_array($action['sound'] ?? null, self::SOUNDS, true) ? ['do' => $do, 'sound' => $action['sound']] : null,
            default => ['do' => $do],
        };
    }

    protected function variable(array $blueprint, string $key, string $scope, bool $board): bool
    {
        $variable = $blueprint['variables'][$key] ?? null;

        return $variable !== null && $variable['scope'] === $scope && ($variable['type'] === 'board') === $board;
    }

    protected function int(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    protected function text(mixed $value, int $length): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $value)), 0, $length);
    }

    protected function path(Game $game): string
    {
        return $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory()) . '/' . self::FILE;
    }
}
