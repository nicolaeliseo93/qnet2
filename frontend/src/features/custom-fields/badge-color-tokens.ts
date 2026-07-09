/**
 * The enum-badge color palette, mirroring `BADGE_COLOR_CLASSES` in
 * `features/table/cell-renderers.tsx` (the single source of truth that turns an
 * option's stored color TOKEN into the grid badge classes). An enum option's
 * `color` is one of these token NAMES, never an arbitrary hex — the grid badge
 * looks the token up by name, so a free hex value would render no color. The
 * color picker therefore offers exactly these tokens as clickable swatches.
 *
 * `swatch` classes are spelled out in full (not built as `bg-${token}-500`) so
 * Tailwind's scanner keeps them in the bundle.
 */
export interface BadgeColorToken {
  /** Stored value (also the `customFields.colors.<token>` i18n key). */
  token: string
  /** Static Tailwind class painting the swatch dot. */
  swatch: string
}

export const BADGE_COLOR_TOKENS: readonly BadgeColorToken[] = [
  { token: 'slate', swatch: 'bg-slate-500' },
  { token: 'gray', swatch: 'bg-gray-500' },
  { token: 'red', swatch: 'bg-red-500' },
  { token: 'orange', swatch: 'bg-orange-500' },
  { token: 'amber', swatch: 'bg-amber-500' },
  { token: 'yellow', swatch: 'bg-yellow-500' },
  { token: 'green', swatch: 'bg-green-500' },
  { token: 'emerald', swatch: 'bg-emerald-500' },
  { token: 'teal', swatch: 'bg-teal-500' },
  { token: 'blue', swatch: 'bg-blue-500' },
  { token: 'indigo', swatch: 'bg-indigo-500' },
  { token: 'violet', swatch: 'bg-violet-500' },
  { token: 'purple', swatch: 'bg-purple-500' },
  { token: 'pink', swatch: 'bg-pink-500' },
]

/** Swatch class for a stored token, or undefined when unset/unknown. */
export function swatchClassFor(token: string | null | undefined): string | undefined {
  return BADGE_COLOR_TOKENS.find((entry) => entry.token === token)?.swatch
}
