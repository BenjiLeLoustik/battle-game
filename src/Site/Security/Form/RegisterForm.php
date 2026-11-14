<?php

namespace App\Site\Security\Form;

use App\Entity\User;
use App\Shared\Translation\Translator;
use NeoPHP\Component\Form\Contract\AbstractForm;
use NeoPHP\Component\Form\Model\FormBuilder;
use NeoPHP\Component\Form\Type\CheckboxType;
use NeoPHP\Component\Form\Type\EmailType;
use NeoPHP\Component\Form\Type\PasswordType;
use NeoPHP\Component\Form\Type\TextType;
use NeoPHP\Component\Validator\Constraint\IsTrue;
use NeoPHP\Component\Validator\Constraint\Length;
use NeoPHP\Component\Validator\Constraint\NotBlank;

class RegisterForm extends AbstractForm
{
    protected ?string $entityClass = User::class;

    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder->add('firstName', TextType::class, [
            'label' => 'site.auth.first_name',
            'attr' => [
                'autocomplete' => 'given-name',
            ]
        ]);

        $builder->add('lastName', TextType::class, [
            'label' => 'site.auth.last_name',
            'attr' => [
                'autocomplete' => 'family-name',
            ]
        ]);

        $builder->add('username', TextType::class, [
            'label' => 'site.auth.username',
            'help' => 'site.auth.username_help',
            'attr' => [
                'autocomplete' => 'nickname',
            ]
        ]);

        $builder->add('email', EmailType::class, [
            'label' => 'site.auth.email',
            'attr' => [
                'autocomplete' => 'email',
                'placeholder' => Translator::trans('site.auth.email_placeholder', [], 'messages'),
            ]
        ]);

        $builder->add('plainPassword', PasswordType::class, [
            'label' => 'site.auth.password',
            'mapped' => false,
            'attr' => [
                'autocomplete' => 'new-password',
                'placeholder' => Translator::trans('site.auth.password_hint', [], 'messages'),
            ],
            'constraints' => [
                new NotBlank(message: 'user.password.not_blank'),
                new Length(min: 10, max: 4096, minMessage: 'user.password.too_short', maxMessage: 'user.password.too_long')
            ],
        ]);

        $builder->add('agreeTerms', CheckboxType::class, [
            'label' => 'site.auth.agree',
            'mapped' => false,
            'constraints' => [
                new IsTrue(message: 'user.terms.required'),
            ]
        ]);
    }
}