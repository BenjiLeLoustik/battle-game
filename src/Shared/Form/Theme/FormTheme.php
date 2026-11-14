<?php

namespace App\Shared\Form\Theme;

use NeoPHP\Component\Form\Model\FormView;
use NeoPHP\Component\Form\Renderer\FormRenderer;
use NeoPHP\Component\Form\Theme\DefaultTheme;

class FormTheme extends DefaultTheme
{
    public function checkboxRow(FormView $view, array $vars, FormRenderer $renderer): string
    {
        return '<div' . $this->attributes($this->addClass((array) ($vars['row_attr'] ?? []), 'field field--check')) . '>'
            . '<label class="field__check">'
            . $renderer->widget($view, ['attr' => $this->addClass((array) ($vars['attr'] ?? []), 'field__checkbox')])
            . '<span class="field__check-label">' . $this->e($vars['label'] ?? '') . '</span>'
            . '</label>'
            . $renderer->errors($view)
            . '</div>';
    }

    public function formLabel(FormView $view, array $vars, FormRenderer $renderer): string
    {
        return parent::formLabel($view, ['required' => false] + $vars, $renderer);
    }

    public function formErrors(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $errors = (array) ($vars['errors'] ?? []);

        if ($errors === []) {
            return '';
        }

        return '<ul class="field__errors">'
            . implode('', array_map(fn (mixed $error): string => '<li class="field__error">' . $this->e($error) . '</li>', $errors))
            . '</ul>';
    }

    protected function rowAttributes(FormView $view, array $vars): array
    {
        return $this->addClass(parent::rowAttributes($view, $vars), 'field field--form');
    }

    protected function rowWidgetVars(array $vars): array
    {
        $class = 'field__input field__input--large' . (($vars['valid'] ?? true) ? '' : ' field__input--invalid');

        return ['attr' => $this->addClass((array) ($vars['attr'] ?? []), $class)];
    }

    protected function rowLabelVars(array $vars): array
    {
        $label = parent::rowLabelVars($vars);
        $label['label_attr'] = $this->addClass($label['label_attr'], 'field__label');

        return $label;
    }

    protected function rowHelpVars(array $vars): array
    {
        $help = parent::rowHelpVars($vars);
        $help['help_attr'] = $this->addClass($help['help_attr'], 'field__help');

        return $help;
    }
}