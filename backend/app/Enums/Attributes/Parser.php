<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Parser
{
    /**
     * @param  class-string<\App\Parsers\Abstracts\Parser>  $parser
     */
    public function __construct(public string $parser) {}
}
