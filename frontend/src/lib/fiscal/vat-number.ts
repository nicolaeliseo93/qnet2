/**
 * Italian VAT number (partita IVA): eleven digits whose last one is a
 * Luhn-style control digit. The same eleven-digit code is also a legal
 * entity's tax code, so the personal-data schema reuses it for a company card.
 *
 * Mirrors `backend/app/Support/Fiscal/ItalianVatNumber.php` 1:1. The server
 * stays authoritative; this is the client-side echo of the same rule.
 */

const VAT_NUMBER_PATTERN = /^\d{11}$/

/** Uppercase, stripped of separators and of the optional IT country prefix. */
export function normalizeVatNumber(value: string): string {
  const digits = value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '')

  return digits.startsWith('IT') ? digits.slice(2) : digits
}

/** Eleven digits with a valid control digit. */
export function isValidVatNumber(value: string): boolean {
  const code = normalizeVatNumber(value)

  // An all-zero code sums to zero and would otherwise pass the control digit:
  // it is never a real VAT number.
  if (!VAT_NUMBER_PATTERN.test(code) || code === '00000000000') {
    return false
  }

  let sum = 0

  for (let position = 0; position < code.length; position++) {
    let digit = Number(code[position])

    // Even (1-based) positions are doubled, then folded back below 10.
    if (position % 2 === 1) {
      digit *= 2

      if (digit > 9) {
        digit -= 9
      }
    }

    sum += digit
  }

  return sum % 10 === 0
}
