<?php

declare(strict_types=1);

namespace App\CustomFields\Exceptions;

use InvalidArgumentException;

/**
 * Requested a FieldTypeHandler for a `type` string with no
 * config/custom-fields.php mapping (spec 0021 AC-003). A field type is not
 * an Eloquent resource, so — unlike TableRegistry/AuthorizationRegistry's
 * ModelNotFoundException — a small domain-specific exception keeps the
 * failure message meaningful instead of borrowing an Eloquent-flavoured one.
 */
class UnknownFieldTypeException extends InvalidArgumentException
{
    public static function forType(string $type): self
    {
        return new self("Unknown custom field type [{$type}].");
    }
}
