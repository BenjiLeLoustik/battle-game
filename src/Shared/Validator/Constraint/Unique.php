<?php

namespace App\Shared\Validator\Constraint;

use Attribute;
use NeoPHP\Component\Validator\Contract\AbstractConstraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends AbstractConstraint
{
    public string $message = 'error.validation.unique';

    public function __construct(
        ?string $message = null,
        public ?string $entity = null,
        public ?string $field = null,
        ?array $groups = null,
    ) {
        $this->message = $message ?? $this->message;
        $this->setGroups($groups);
    }
}