<?php

namespace App\Site\Security\Form;

use NeoPHP\Component\Form\Model\FormBuilder;
use NeoPHP\Component\Form\Type\CheckboxType;
use NeoPHP\Component\Validator\Constraint\IsTrue;

class DiscordRegisterForm extends GuestForm
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('agreeTerms', CheckboxType::class, [
            'label' => 'site.auth.agree',
            'constraints' => [
                new IsTrue(message: 'user.terms.required'),
            ]
        ]);
    }
}
