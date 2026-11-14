<?php

namespace App\Shared\Validator\Constraint;

use NeoPHP\Component\Validator\Context\ExecutionContext;
use NeoPHP\Component\Validator\Contract\AbstractConstraintValidator;
use NeoPHP\Component\Validator\Contract\ConstraintInterface;
use NeoPHP\Package\Orm\Contract\EntityManagerInterface;

class UniqueValidator extends AbstractConstraintValidator
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(mixed $value, ConstraintInterface $constraint, ExecutionContext $context): void
    {
        $this->expect($constraint, Unique::class);
        $object = $context->getObject();
        $class = $constraint->entity ?? ($object !== null ? $object::class : null);

        if ($this->isEmpty($value) || $class === null) {
            return;
        }

        $field = $constraint->field ?? preg_replace('/^.*\./', '', $context->getPropertyPath());
        $found = $this->entityManager->getRepository($class)->findOneBy([$field => $value]);

        if ($found !== null && $found !== $object) {
            $context->addViolation($constraint->message);
        }
    }
}