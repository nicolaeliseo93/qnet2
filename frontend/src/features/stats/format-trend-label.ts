const YEAR_MONTH_PATTERN = /^(\d{4})-(\d{2})$/

/**
 * Formats a trend point's `YYYY-MM` label (contract, spec 0026) into a short,
 * locale-aware month name (e.g. "Feb 2026" / "feb 2026"). Falls back to the
 * raw label for any value that does not match the expected shape, so an
 * unexpected backend format degrades gracefully instead of crashing.
 */
export function formatTrendLabel(label: string, locale: string): string {
  const match = YEAR_MONTH_PATTERN.exec(label)
  if (!match) {
    return label
  }

  const [, year, month] = match
  const date = new Date(Number(year), Number(month) - 1, 1)

  return new Intl.DateTimeFormat(locale, { month: 'short', year: 'numeric' }).format(date)
}
