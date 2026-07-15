import type { AdvancedFilterOption } from '@/features/table/advanced-filters/types'

/**
 * Resolves a `<Select>`/`<MultiSelect>` string value (Radix primitives only
 * speak strings) back to the option's originally-typed value, so a numeric
 * option (e.g. an enum backed by an integer id) round-trips as a number in
 * the applied filter payload, matching the backend's typed allow-list.
 * Falls back to the raw string when no option matches (defensive).
 */
export function resolveOptionValue(
  options: AdvancedFilterOption[],
  raw: string,
): string | number {
  const match = options.find((option) => String(option.value) === raw)
  return match ? match.value : raw
}

/** Keeps only the entries that are actually string/number (defensive against a malformed stored value). */
export function toOptionValueArray(value: unknown): (string | number)[] {
  return Array.isArray(value)
    ? value.filter(
        (entry): entry is string | number =>
          typeof entry === 'string' || typeof entry === 'number',
      )
    : []
}
