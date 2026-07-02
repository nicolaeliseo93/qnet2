<?php

namespace App\Enums\Concerns;

use App\DataObjects\Enums\EnumMeta;
use App\Enums\Attributes\Color;
use App\Enums\Attributes\HiddenOnForm;
use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use ReflectionClassConstant;

/**
 * Reader trait that exposes, for a backed enum, the presentation metadata
 * declared on each case through PHP attributes (Label, Color, Icon, IsDefault,
 * HiddenOnForm — see App\Enums\Attributes).
 *
 * A missing attribute never throws: it resolves to null/false. The raw
 * attribute payload is reflected once per FQCN+case and cached in a static map;
 * the label translation, however, is resolved on every read (never cached) so a
 * runtime locale change is honoured immediately.
 *
 * The trait reads only the five presentation attributes above; unrelated
 * attributes on the same case (Path, Parser, Percentage, ...) are ignored.
 *
 * @mixin \BackedEnum
 */
trait HasMeta
{
    /**
     * The human-facing label: __() of the EN source string in #[Label]. When no
     * #[Label] is declared the case name is humanized as a fallback (e.g.
     * "ActionUrl" → "Action Url"). Resolved on every call so a locale switch is
     * reflected without clearing any cache.
     */
    public function label(): string
    {
        $source = self::resolveMeta($this->name)['label'];

        if ($source === null) {
            return self::humanize($this->name);
        }

        return __($source);
    }

    /**
     * The #[Color] value, or null when the attribute is absent.
     */
    public function color(): ?string
    {
        return self::resolveMeta($this->name)['color'];
    }

    /**
     * The #[Icon] value, or null when the attribute is absent.
     */
    public function icon(): ?string
    {
        return self::resolveMeta($this->name)['icon'];
    }

    /**
     * Whether the case is flagged #[IsDefault(true)] (false when absent).
     */
    public function isDefault(): bool
    {
        return self::resolveMeta($this->name)['isDefault'];
    }

    /**
     * Whether the case is flagged #[HiddenOnForm(true)] (false when absent).
     * The endpoint/caller is responsible for filtering these out of options.
     */
    public function hiddenOnForm(): bool
    {
        return self::resolveMeta($this->name)['hiddenOnForm'];
    }

    /**
     * The aggregated, client-facing metadata for the current case.
     */
    public function meta(): EnumMeta
    {
        return new EnumMeta(
            value: $this->value,
            label: $this->label(),
            color: $this->color(),
            icon: $this->icon(),
            isDefault: $this->isDefault(),
            hiddenOnForm: $this->hiddenOnForm(),
        );
    }

    /**
     * Metadata for every case, in declaration order. The caller (e.g. the API
     * endpoint) decides whether to filter out hiddenOnForm cases.
     *
     * @return array<int, EnumMeta>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): EnumMeta => $case->meta(),
            self::cases(),
        );
    }

    /**
     * The case flagged #[IsDefault(true)], or null when no case declares it.
     */
    public static function default(): ?static
    {
        foreach (self::cases() as $case) {
            if ($case->isDefault()) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Reflect (once, then cache) the locale-independent attribute payload for a
     * single case. Reads only the five presentation attributes; anything else
     * is ignored. Missing attribute → null/false.
     *
     * @return array{label: ?string, color: ?string, icon: ?string, isDefault: bool, hiddenOnForm: bool}
     */
    private static function resolveMeta(string $case): array
    {
        $key = static::class.'::'.$case;

        if (EnumMetaCache::has($key)) {
            return EnumMetaCache::get($key);
        }

        $constant = new ReflectionClassConstant(static::class, $case);

        $label = $constant->getAttributes(Label::class)[0] ?? null;
        $color = $constant->getAttributes(Color::class)[0] ?? null;
        $icon = $constant->getAttributes(Icon::class)[0] ?? null;
        $isDefault = $constant->getAttributes(IsDefault::class)[0] ?? null;
        $hiddenOnForm = $constant->getAttributes(HiddenOnForm::class)[0] ?? null;

        return EnumMetaCache::put($key, [
            'label' => $label?->newInstance()->label,
            'color' => $color?->newInstance()->color,
            'icon' => $icon?->newInstance()->icon,
            'isDefault' => $isDefault?->newInstance()->isDefault ?? false,
            'hiddenOnForm' => $hiddenOnForm?->newInstance()->hiddenOnForm ?? false,
        ]);
    }

    /**
     * Humanize a PascalCase/camelCase case name into a spaced Title Case string,
     * used as the label fallback when no #[Label] is declared.
     */
    private static function humanize(string $name): string
    {
        return trim(preg_replace('/(?<!^)(?=[A-Z])/', ' ', $name));
    }
}
