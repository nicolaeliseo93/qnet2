/**
 * Colored badge classes for the `project_status` color token, used by the
 * card grid. Deliberately duplicates the identically-named map in
 * `column-renderers.tsx` (spec 0026 card grid vs. spec 0025 manual-code
 * modules were built concurrently on that file) rather than importing from
 * it, to avoid a merge collision — dedupe once both land.
 */
export const STATUS_BADGE_CLASSES: Record<string, string> = {
  slate: 'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
  gray: 'border-transparent bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
  red: 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
  orange: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-200',
  amber: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
  yellow: 'border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
  green: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200',
  emerald: 'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  teal: 'border-transparent bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-200',
  blue: 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
  indigo: 'border-transparent bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200',
  violet: 'border-transparent bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
  purple: 'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-200',
  pink: 'border-transparent bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-200',
}

/** Resolves a backend `#[Color]` token to its badge classes, `undefined` when unmapped/null. */
export function statusBadgeClassName(color: string | null): string | undefined {
  return color ? STATUS_BADGE_CLASSES[color] : undefined
}
