<?php

namespace App\Site\Security\Form;

use App\Entity\User;
use App\Shared\Translation\Translator;
use App\Shared\Validator\Constraint\Unique;
use NeoPHP\Component\Form\Contract\AbstractForm;
use NeoPHP\Component\Form\Model\FormBuilder;
use NeoPHP\Component\Form\Type\TextType;
use NeoPHP\Component\Validator\Constraint\NotBlank;
use NeoPHP\Component\Validator\Constraint\Regex;

class GuestForm extends AbstractForm
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder->add('username', TextType::class, [
            'label' => 'site.auth.username',
            'help' => 'site.auth.username_help',
            'attr' => [
                'autocomplete' => 'nickname',
                'maxlength' => 30,
                'placeholder' => Translator::trans('site.auth.guest_placeholder', [], 'messages'),
            ],
            'constraints' => [
                new NotBlank(message: 'user.username.not_blank'),
                new Regex(pattern: User::USERNAME_PATTERN, message: 'user.username.invalid'),
                new Unique(message: 'user.username.taken', entity: User::class, field: 'username'),
            ]
        ]);
    }
}
