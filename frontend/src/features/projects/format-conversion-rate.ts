/** Em dash shown for a `null` (undefined denominator, BR-1) conversion rate. */
export const EMPTY_CONVERSION_RATE = '—'

/** Formats a `null`-able integer percentage as `"NN%"`, `EMPTY_CONVERSION_RATE` when null. */
export function formatConversionRate(rate: number | null): string {
  return rate === null ? EMPTY_CONVERSION_RATE : `${rate}%`
}
