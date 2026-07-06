<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class HiddenOnForm
{
    public function __construct(public bool $hiddenOnForm) {}
}
