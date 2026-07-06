<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Path
{
    public function __construct(
        public string $path,
    ) {}
}
