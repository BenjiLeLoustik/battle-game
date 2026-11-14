<?php

namespace App\Admin\Studio\Blueprint;

use App\Entity\Game;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;

class BlueprintGenerator
{
    private const SKELETON = __DIR__ . '/Skeleton';
    private const ACTORLESS = ['game_start', 'round_start', 'timeout'];
    private const SYSTEM_TEXTS = [
        'ready' => 'Prépare-toi…',
        'your_turn' => 'À toi de jouer !',
        'their_turn' => 'Au tour de {name}',
        'play' => 'À toi de jouer !',
        'waiting' => 'En attente des autres joueurs…',
        'reveal' => 'Fin de la manche',
        'finished' => 'Partie terminée',
        'round' => 'Manche {current} / {total}',
        'seconds' => '{seconds} s',
        'players' => 'Joueurs',
        'send' => 'Valider',
        'answer' => 'Ta réponse',
        'you' => 'toi',
    ];

    public function __construct(
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    public function write(Game $game, array $blueprint): array
    {
        $root = $this->root($game);
        $files = $this->files($game, $blueprint, json_decode((string) file_get_contents($root . '/game.json'), true) ?: []);

        foreach ($files as $path => $content) {
            if (!is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0777, true);
            }

            file_put_contents($root . '/' . $path, $content);
        }

        return array_keys($files);
    }

    public function files(Game $game, array $blueprint, array $manifest): array
    {
        $manager = (string) ($manifest['manager'] ?? '');
        $server = (string) ($manifest['server'] ?? '');
        $namespace = substr($manager, 0, (int) strrpos($manager, '\\'));
        $managerClass = substr($manager, (int) strrpos($manager, '\\') + 1);
        $serverClass = substr($server, (int) strrpos($server, '\\') + 1);
        $replacements = ['{{namespace}}' => $namespace, '{{manager}}' => $managerClass, '{{server}}' => $serverClass];

        return [
            'game.json' => $this->json($this->manifest($manifest, $blueprint)),
            'src/' . $managerClass . '.php' => strtr($this->skeleton('Manager.php'), $replacements),
            'src/' . $serverClass . '.php' => strtr($this->skeleton('Server.php'), $replacements + $this->server($blueprint)),
            'assets/js/client.js' => strtr($this->skeleton('client.js'), ['{{layout}}' => $this->layout($blueprint)]),
            'assets/css/client.css' => $this->skeleton('client.css') . $this->customCss($blueprint),
            'translations/game.fr.yaml' => $this->translations($blueprint),
        ];
    }

    public function manifest(array $manifest, array $blueprint): array
    {
        $rounds = $blueprint['rounds'];
        $time = $blueprint['time'];
        $minimum = $blueprint['mode'] === 'turns' ? 2 : 1;
        $maximum = max(2, $blueprint['mode'] === 'turns' ? min(4, (int) ($manifest['players']['max'] ?? 2)) : min(8, (int) ($manifest['players']['max'] ?? 8)));

        return array_merge($manifest, [
            'players' => ['min' => $minimum, 'max' => $maximum],
            'solo' => $minimum === 1,
            'client' => 'js/client.js',
            'style' => 'css/client.css',
            'settings' => [
                ['name' => 'rounds', 'type' => 'choice', 'choices' => array_values(array_unique([max(1, intdiv($rounds, 2)), $rounds, min(50, $rounds * 2)])), 'default' => $rounds, 'label' => 'Nombre de manches'],
                ['name' => 'time', 'type' => 'choice', 'choices' => array_values(array_unique([max(3, intdiv($time, 2)), $time, min(600, $time * 2)])), 'default' => $time, 'label' => $blueprint['mode'] === 'turns' ? 'Temps par tour' : 'Temps par manche', 'suffix' => ' s'],
            ],
            'stats' => [['key' => 'score', 'label' => 'Meilleur score', 'aggregate' => 'max', 'format' => '{value} pts', 'leaderboard' => 'Meilleurs scores']],
            'nocode' => true,
        ]);
    }

    protected function server(array $blueprint): array
    {
        $variables = array_map(fn (array $variable): array => [
            'key' => $variable['key'],
            'scope' => $variable['scope'],
            'type' => $variable['type'],
            'initial' => $variable['type'] === 'number' ? (is_numeric($variable['initial']) ? 0 + $variable['initial'] : 0) : $variable['initial'],
            'size' => $variable['size'],
            'reset' => $variable['reset'],
        ], $blueprint['variables']);
        $elements = [];
        $grids = [];
        $columns = [];

        foreach ($blueprint['elements'] as $element) {
            if (in_array($element['type'], ['button', 'grid', 'input', 'choices', 'custom'], true)) {
                $elements[$element['id']] = $element['type'];
            }

            if ($element['type'] === 'grid') {
                $grids[$element['id']] = $element['variable'];
                $columns[$element['variable']] ??= $element['columns'];
            }
        }

        foreach ($blueprint['variables'] as $variable) {
            if ($variable['type'] === 'board') {
                $columns[$variable['key']] ??= max(1, (int) round(sqrt($variable['size'])));
            }
        }

        $hidden = array_values(array_map(fn (array $variable): array => [$variable['scope'], $variable['key']], array_filter($blueprint['variables'], fn (array $variable): bool => $variable['hidden'])));
        $dispatch = [];
        $methods = [];

        foreach (array_values($blueprint['rules']) as $index => $rule) {
            $method = 'rule' . ($index + 1);
            $test = '$event === ' . $this->export($rule['when']) . ($rule['element'] !== '' ? ' && $element === ' . $this->export($rule['element']) : '');
            $personal = $this->personal($rule);
            $call = in_array($rule['when'], self::ACTORLESS, true) && $personal
                ? "            foreach (\$state['players'] as \$each) {\n                \$state = self::{$method}(\$state, \$actor ?? \$each, \$context);\n            }"
                : "            \$state = self::{$method}(\$state, \$actor, \$context);";
            $dispatch[$test][] = $call;
            $methods[] = $this->method($method, $rule, $personal);
        }

        $dispatch = array_map(fn (string $test, array $calls): string => "        if ({$test}) {\n" . implode("\n", $calls) . "\n        }", array_keys($dispatch), $dispatch);

        return [
            '{{mode}}' => $this->export($blueprint['mode']),
            '{{reveal}}' => (string) ($blueprint['reveal'] * 1000),
            '{{rounds}}' => (string) $blueprint['rounds'],
            '{{time}}' => (string) $blueprint['time'],
            '{{variables}}' => $this->export($variables),
            '{{elements}}' => $this->export($elements),
            '{{grids}}' => $this->export($grids),
            '{{columns}}' => $this->export($columns),
            '{{hidden}}' => $this->export($hidden),
            '{{dispatch}}' => $dispatch === [] ? '' : implode("\n\n", $dispatch) . "\n\n",
            '{{rules}}' => $methods === [] ? '' : "\n" . implode("\n\n", $methods) . "\n",
        ];
    }

    protected function method(string $name, array $rule, bool $personal): string
    {
        $lines = [];

        if ($personal) {
            $lines[] = "        if (\$actor === null) {\n            return \$state;\n        }\n";
        }

        if ($rule['if'] !== []) {
            $conditions = implode(' && ', array_map(fn (array $condition): string => $this->condition($condition), $rule['if']));
            $lines[] = "        if (!({$conditions})) {\n            return \$state;\n        }\n";
        }

        foreach ($rule['then'] as $position => $action) {
            $lines[] = $this->statement($action, $rule['id'] . '_' . $position);
        }

        return "    protected static function {$name}(array \$state, ?string \$actor, array \$context): array\n    {\n" . implode("\n", $lines) . "\n        return \$state;\n    }";
    }

    protected function condition(array $condition): string
    {
        return match ($condition['check']) {
            'compare' => 'self::compare(' . $this->expression($condition['left']) . ', ' . $this->export($condition['op']) . ', ' . $this->expression($condition['right']) . ')',
            'empty' => "(int) (\$state['vars'][\$context['board'] ?? ''][\$context['cell'] ?? -1] ?? 1) === 0",
            'line' => 'self::line($state, ' . $this->export($condition['board']) . ', self::mark($state, $actor), ' . $condition['length'] . ')',
            'full' => "!in_array(0, \$state['vars'][" . $this->export($condition['board']) . "] ?? [0], true)",
        };
    }

    protected function statement(array $action, string $key): string
    {
        return match ($action['do']) {
            'set' => '        ' . $this->target($action['target']) . ' = ' . $this->expression($action['value']) . ";\n",
            'add' => '        ' . $this->target($action['target']) . ' = self::number(' . $this->target($action['target']) . ' ?? 0) + self::number(' . $this->expression($action['value']) . ");\n",
            'random' => '        ' . $this->target($action['target']) . ' = random_int(' . $action['min'] . ', ' . $action['max'] . ");\n",
            'set_cell' => "        if (isset(\$context['cell'], \$state['vars'][" . $this->export($action['board']) . "][\$context['cell']])) {\n            \$state['vars'][" . $this->export($action['board']) . "][\$context['cell']] = " . $this->expression($action['value']) . ";\n        }\n",
            'clear_board' => "        \$state['vars'][" . $this->export($action['board']) . "] = array_fill(0, count(\$state['vars'][" . $this->export($action['board']) . "]), 0);\n",
            'message' => "        \$state['messages'][" . ($action['to'] === 'all' ? "'*'" : "\$actor ?? '*'") . "] = ['key' => " . $this->export('m_' . $key) . ", 'by' => \$actor];\n",
            'done' => "        if (\$actor !== null) {\n            \$state['done'][\$actor] = true;\n        }\n",
            'next_turn', 'end_round', 'finish' => "        \$state['pending'][" . $this->export($action['do']) . "] = true;\n",
            'sound' => "        \$state['fx'] = ['seq' => \$state['fx']['seq'] + 1, 'name' => " . $this->export($action['sound']) . ", 'by' => \$actor];\n",
        };
    }

    protected function target(array $operand): string
    {
        return match ($operand['kind']) {
            'var' => "\$state['vars'][" . $this->export($operand['value']) . ']',
            'my' => "\$state['pvars'][\$actor][" . $this->export($operand['value']) . ']',
            default => "\$state['scores'][\$actor]",
        };
    }

    protected function expression(array $operand): string
    {
        return match ($operand['kind']) {
            'number' => $this->export(0 + $operand['value']),
            'text' => $this->export($operand['value']),
            'var' => "(\$state['vars'][" . $this->export($operand['value']) . '] ?? 0)',
            'my' => "(\$state['pvars'][\$actor ?? ''][" . $this->export($operand['value']) . '] ?? 0)',
            'score' => "(\$state['scores'][\$actor ?? ''] ?? 0)",
            'answer' => "(\$context['answer'] ?? '')",
            'cell' => "(\$context['cell'] ?? -1)",
            'cellvalue' => "(\$state['vars'][\$context['board'] ?? ''][\$context['cell'] ?? -1] ?? 0)",
            'round' => "\$state['round']",
            'random' => 'random_int(' . $operand['min'] . ', ' . $operand['max'] . ')',
            'mark' => 'self::mark($state, $actor)',
            'players' => "count(\$state['players'])",
        };
    }

    protected function personal(array $rule): bool
    {
        $json = json_encode($rule);

        return str_contains($json, '"kind":"my"') || str_contains($json, '"kind":"score"') || str_contains($json, '"kind":"mark"') || str_contains($json, '"do":"done"') || str_contains($json, '"check":"line"') || (str_contains($json, '"do":"message"') && str_contains($json, '"to":"me"'));
    }

    protected function layout(array $blueprint): string
    {
        $boards = array_column(array_filter($blueprint['variables'], fn (array $variable): bool => $variable['type'] === 'board'), 'size', 'key');
        $hidden = array_values(array_map(fn (array $variable): string => $variable['key'], array_filter($blueprint['variables'], fn (array $variable): bool => $variable['hidden'])));
        $scopes = array_column($blueprint['variables'], 'scope', 'key');
        $elements = array_map(fn (array $element): array => array_filter([
            'id' => $element['id'],
            'type' => $element['type'],
            'variant' => $element['variant'],
            'inline' => $element['inline'] ?: null,
            'parent' => ($element['parent'] ?? '') ?: null,
            'variable' => $element['variable'] ?: null,
            'scope' => $element['variable'] !== '' && $element['variable'] !== 'score' ? ($scopes[$element['variable']] ?? null) : null,
            'columns' => in_array($element['type'], ['grid', 'choices'], true) ? $element['columns'] : null,
            'size' => $element['type'] === 'grid' ? ($boards[$element['variable']] ?? 9) : null,
            'options' => $element['type'] === 'choices' ? count($element['options'] ?? []) : null,
            'src' => $element['type'] === 'image' ? ($element['src'] ?? '') : null,
            'html' => $element['type'] === 'custom' ? ($element['html'] ?? '') : null,
            'script' => $element['type'] === 'custom' && trim($element['script'] ?? '') !== '' ? $element['script'] : null,
        ], fn (mixed $value): bool => $value !== null), $blueprint['elements']);

        return json_encode(['mode' => $blueprint['mode'], 'hidden' => $hidden, 'elements' => array_values($elements)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    protected function translations(array $blueprint): string
    {
        $texts = self::SYSTEM_TEXTS;

        foreach ($blueprint['elements'] as $element) {
            if ($element['text'] !== '') {
                $texts[$element['id']] = $element['text'];
            }

            foreach ($element['options'] ?? [] as $index => $option) {
                $texts[$element['id'] . '_o' . $index] = $option;
            }
        }

        foreach ($blueprint['rules'] as $rule) {
            foreach ($rule['then'] as $position => $action) {
                if ($action['do'] === 'message') {
                    $texts['m_' . $rule['id'] . '_' . $position] = $action['text'];
                }
            }
        }

        return "texts:\n" . implode('', array_map(fn (string $key, string $text): string => '    ' . $key . ': ' . json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", array_keys($texts), $texts));
    }

    protected function customCss(array $blueprint): string
    {
        $blocks = array_filter(array_map(fn (array $element): string => trim($element['css'] ?? ''), $blueprint['elements']));

        return $blocks === [] ? '' : "\n" . implode("\n\n", $blocks) . "\n";
    }

    protected function export(mixed $value): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $list = array_is_list($value);
        $items = array_map(fn (mixed $key, mixed $item): string => ($list ? '' : var_export($key, true) . ' => ') . $this->export($item), array_keys($value), $value);

        return '[' . implode(', ', $items) . ']';
    }

    protected function skeleton(string $file): string
    {
        return (string) file_get_contents(self::SKELETON . '/' . $file . '.dist');
    }

    protected function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    protected function root(Game $game): string
    {
        return $this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/' . basename($game->getDirectory());
    }
}
