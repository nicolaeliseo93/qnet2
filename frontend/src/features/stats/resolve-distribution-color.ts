import { STATUS_BADGE_CLASSES } from '@/features/projects/status-badge-classes'

/** CSS shade used for every resolved token, matching the mid-tone already used by the badge palette. */
const TOKEN_SHADE = 500

/**
 * A `distribution` widget item's `color` is a backend color TOKEN read from a
 * lookup table (e.g. `project_statuses.color`: "teal", "slate", "amber"),
 * NOT a literal CSS color as the frozen contract's example ("#22c55e")
 * suggested: several tokens ("slate", "amber") are not valid standalone CSS
 * color keywords. `color` is DB-controlled content (editable by whoever
 * administers the lookup), so it is never interpolated into an inline style
 * unvalidated — only a token from the allow-list below resolves to a color.
 *
 * The allow-list is `STATUS_BADGE_CLASSES`'s own key set — the vocabulary
 * already authoritative for status badges — so the two never drift apart.
 * A recognized token resolves to Tailwind's OWN theme CSS variable
 * (`--color-<token>-500`, shipped by `@import "tailwindcss"`; no new palette
 * invented here). An unrecognized token or `null` resolves to `null`,
 * letting `StatBarList` fall back to its own default bar color.
 */
export function resolveDistributionColor(color: string | null): string | null {
  // `hasOwnProperty`, not the `in` operator: an object literal's inherited
  // `Object.prototype` keys (e.g. a "constructor" token) must never pass the
  // allow-list check.
  const isKnownToken =
    typeof color === 'string' && Object.prototype.hasOwnProperty.call(STATUS_BADGE_CLASSES, color)

  if (!isKnownToken) {
    return null
  }

  return `var(--color-${color}-${TOKEN_SHADE})`
}
