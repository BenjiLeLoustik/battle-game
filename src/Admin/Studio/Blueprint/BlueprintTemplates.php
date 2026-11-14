<?php

namespace App\Admin\Studio\Blueprint;

class BlueprintTemplates
{
    public const NAMES = ['blank', 'quiz', 'grid', 'race'];

    public static function get(string $name): array
    {
        return match ($name) {
            'quiz' => self::quiz(),
            'grid' => self::grid(),
            'race' => self::race(),
            default => self::blank(),
        };
    }

    protected static function blank(): array
    {
        return [
            'mode' => 'simultaneous',
            'rounds' => 3,
            'time' => 20,
            'reveal' => 2,
            'variables' => [],
            'elements' => [
                ['id' => 'e1', 'type' => 'text', 'text' => 'Manche {round} / {rounds}', 'variant' => 'title'],
                ['id' => 'e2', 'type' => 'button', 'text' => 'Clique-moi'],
                ['id' => 'e3', 'type' => 'scores'],
            ],
            'rules' => [
                ['id' => 'r1', 'when' => 'click', 'element' => 'e2', 'if' => [], 'then' => [['do' => 'add', 'target' => ['kind' => 'score'], 'value' => ['kind' => 'number', 'value' => '1']], ['do' => 'sound', 'sound' => 'pop']]],
            ],
        ];
    }

    protected static function quiz(): array
    {
        $score = ['kind' => 'score'];

        return [
            'mode' => 'simultaneous',
            'rounds' => 5,
            'time' => 15,
            'reveal' => 2,
            'variables' => [
                ['key' => 'a', 'scope' => 'game', 'type' => 'number', 'initial' => '0', 'reset' => true],
                ['key' => 'b', 'scope' => 'game', 'type' => 'number', 'initial' => '0', 'reset' => true],
                ['key' => 'total', 'scope' => 'game', 'type' => 'number', 'initial' => '0', 'reset' => true, 'hidden' => true],
            ],
            'elements' => [
                ['id' => 'e1', 'type' => 'text', 'text' => 'Calcul {round} / {rounds}', 'variant' => 'hint'],
                ['id' => 'e2', 'type' => 'text', 'text' => 'Combien font {var.a} + {var.b} ?', 'variant' => 'title'],
                ['id' => 'e3', 'type' => 'timer'],
                ['id' => 'e4', 'type' => 'input', 'text' => 'Valider'],
                ['id' => 'e5', 'type' => 'message'],
                ['id' => 'e6', 'type' => 'scores'],
            ],
            'rules' => [
                ['id' => 'r1', 'when' => 'round_start', 'if' => [], 'then' => [
                    ['do' => 'random', 'target' => ['kind' => 'var', 'value' => 'a'], 'min' => 2, 'max' => 30],
                    ['do' => 'random', 'target' => ['kind' => 'var', 'value' => 'b'], 'min' => 2, 'max' => 30],
                    ['do' => 'set', 'target' => ['kind' => 'var', 'value' => 'total'], 'value' => ['kind' => 'var', 'value' => 'a']],
                    ['do' => 'add', 'target' => ['kind' => 'var', 'value' => 'total'], 'value' => ['kind' => 'var', 'value' => 'b']],
                ]],
                ['id' => 'r2', 'when' => 'answer', 'element' => 'e4', 'if' => [['check' => 'compare', 'left' => ['kind' => 'answer'], 'op' => '==', 'right' => ['kind' => 'var', 'value' => 'total']]], 'then' => [
                    ['do' => 'add', 'target' => $score, 'value' => ['kind' => 'number', 'value' => '10']],
                    ['do' => 'message', 'text' => 'Bonne réponse ! +10', 'to' => 'me'],
                    ['do' => 'sound', 'sound' => 'correct'],
                    ['do' => 'done'],
                ]],
                ['id' => 'r3', 'when' => 'answer', 'element' => 'e4', 'if' => [['check' => 'compare', 'left' => ['kind' => 'answer'], 'op' => '!=', 'right' => ['kind' => 'var', 'value' => 'total']]], 'then' => [
                    ['do' => 'message', 'text' => 'Raté…', 'to' => 'me'],
                    ['do' => 'sound', 'sound' => 'wrong'],
                    ['do' => 'done'],
                ]],
            ],
        ];
    }

    protected static function grid(): array
    {
        return [
            'mode' => 'turns',
            'rounds' => 1,
            'time' => 20,
            'reveal' => 2,
            'variables' => [
                ['key' => 'board', 'scope' => 'game', 'type' => 'board', 'size' => 9],
            ],
            'elements' => [
                ['id' => 'e1', 'type' => 'text', 'text' => 'Au tour de {player}', 'variant' => 'title'],
                ['id' => 'e2', 'type' => 'timer'],
                ['id' => 'e3', 'type' => 'grid', 'variable' => 'board', 'columns' => 3],
                ['id' => 'e4', 'type' => 'message'],
                ['id' => 'e5', 'type' => 'scores'],
            ],
            'rules' => [
                ['id' => 'r1', 'when' => 'cell', 'element' => 'e3', 'if' => [['check' => 'empty']], 'then' => [
                    ['do' => 'set_cell', 'board' => 'board', 'value' => ['kind' => 'mark']],
                    ['do' => 'sound', 'sound' => 'place'],
                    ['do' => 'next_turn'],
                ]],
                ['id' => 'r2', 'when' => 'cell', 'element' => 'e3', 'if' => [['check' => 'line', 'board' => 'board', 'length' => 3]], 'then' => [
                    ['do' => 'add', 'target' => ['kind' => 'score'], 'value' => ['kind' => 'number', 'value' => '1']],
                    ['do' => 'message', 'text' => '{name} aligne trois symboles et gagne !', 'to' => 'all'],
                    ['do' => 'sound', 'sound' => 'win'],
                    ['do' => 'finish'],
                ]],
                ['id' => 'r3', 'when' => 'cell', 'element' => 'e3', 'if' => [['check' => 'full', 'board' => 'board']], 'then' => [
                    ['do' => 'message', 'text' => 'Grille pleine : égalité !', 'to' => 'all'],
                    ['do' => 'finish'],
                ]],
            ],
        ];
    }

    protected static function race(): array
    {
        return [
            'mode' => 'simultaneous',
            'rounds' => 1,
            'time' => 30,
            'reveal' => 2,
            'variables' => [
                ['key' => 'goal', 'scope' => 'game', 'type' => 'number', 'initial' => '30'],
            ],
            'elements' => [
                ['id' => 'e1', 'type' => 'text', 'text' => 'Premier à {var.goal} clics !', 'variant' => 'title'],
                ['id' => 'e2', 'type' => 'counter', 'text' => 'Tes clics', 'variable' => 'score'],
                ['id' => 'e3', 'type' => 'button', 'text' => 'CLIQUE !', 'variant' => 'big'],
                ['id' => 'e4', 'type' => 'timer'],
                ['id' => 'e5', 'type' => 'message'],
                ['id' => 'e6', 'type' => 'scores'],
            ],
            'rules' => [
                ['id' => 'r1', 'when' => 'click', 'element' => 'e3', 'if' => [], 'then' => [
                    ['do' => 'add', 'target' => ['kind' => 'score'], 'value' => ['kind' => 'number', 'value' => '1']],
                    ['do' => 'sound', 'sound' => 'pop'],
                ]],
                ['id' => 'r2', 'when' => 'click', 'element' => 'e3', 'if' => [['check' => 'compare', 'left' => ['kind' => 'score'], 'op' => '>=', 'right' => ['kind' => 'var', 'value' => 'goal']]], 'then' => [
                    ['do' => 'message', 'text' => '{name} remporte la course !', 'to' => 'all'],
                    ['do' => 'sound', 'sound' => 'win'],
                    ['do' => 'finish'],
                ]],
            ],
        ];
    }
}
