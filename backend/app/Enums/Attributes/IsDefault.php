<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class IsDefault
{
    public function __construct(public bool $isDefault) {}
}
