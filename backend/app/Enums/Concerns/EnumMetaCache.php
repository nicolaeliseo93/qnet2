<?php

namespace App\Enums\Concerns;

/**
 * Process-local cache of reflected, locale-independent enum-case attribute
 * payloads, keyed by "FQCN::caseName".
 *
 * Enums cannot declare non-constant properties (not even through a trait), so
 * the HasMeta reader cannot hold its reflection cache on the enum itself; this
 * tiny registry owns that static map instead. The translatable label *source*
 * string is cached here, but its translation is resolved at read time by the
 * reader so a runtime locale change is always honoured.
 *
 * @internal Used only by App\Enums\Concerns\HasMeta.
 */
final class EnumMetaCache
{
    /**
     * @var array<string, array{label: ?string, color: ?string, icon: ?string, isDefault: bool, hiddenOnForm: bool}>
     */
    private static array $store = [];

    public static function has(string $key): bool
    {
        return isset(self::$store[$key]);
    }

    /**
     * @return array{label: ?string, color: ?string, icon: ?string, isDefault: bool, hiddenOnForm: bool}
     */
    public static function get(string $key): array
    {
        return self::$store[$key];
    }

    /**
     * @param  array{label: ?string, color: ?string, icon: ?string, isDefault: bool, hiddenOnForm: bool}  $value
     * @return array{label: ?string, color: ?string, icon: ?string, isDefault: bool, hiddenOnForm: bool}
     */
    public static function put(string $key, array $value): array
    {
        return self::$store[$key] = $value;
    }
}
