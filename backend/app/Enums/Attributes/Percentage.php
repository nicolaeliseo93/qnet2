<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute]
class Percentage
{
    public function __construct(
        public float $percentage
    )
    {
    }
}
