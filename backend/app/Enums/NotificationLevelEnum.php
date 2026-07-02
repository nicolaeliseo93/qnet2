<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Severity level carried by a notification payload. Single source of truth for
 * the allowed values, shared by GenericNotification (write) and the
 * NotificationData value object (read/normalize).
 */
enum NotificationLevelEnum: string
{
    use HasMeta;

    #[Label('Info')]
    #[Icon('info')]
    #[Color('blue')]
    #[IsDefault(true)]
    case Info = 'info';

    #[Label('Success')]
    #[Icon('check-circle')]
    #[Color('green')]
    case Success = 'success';

    #[Label('Warning')]
    #[Icon('alert-triangle')]
    #[Color('amber')]
    case Warning = 'warning';

    #[Label('Error')]
    #[Icon('alert-circle')]
    #[Color('red')]
    case Error = 'error';

    /**
     * Resolve a stored/raw value to a level, defaulting to Info for anything
     * unknown or missing — so the API never emits an out-of-contract level.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Info;
    }
}
