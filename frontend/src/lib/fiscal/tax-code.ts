/**
 * Italian personal tax code (codice fiscale): format check, control character
 * verification, omocodia normalization and the decoding of the birth date /
 * gender the code encodes, plus the surname/name triple encoder used to check
 * a code against the anagraphic fields of the same card.
 *
 * Mirrors `backend/app/Support/Fiscal/ItalianTaxCode.php` and
 * `TaxCodeNameEncoder.php` 1:1 — a change here changes those files too. The
 * server stays authoritative; this is the client-side echo of the same rule.
 */

const TAX_CODE_PATTERN =
  /^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/

/** 0-based positions whose digit omocodia may have replaced with a letter. */
const OMOCODIA_POSITIONS = [6, 7, 9, 10, 12, 13, 14]

/** The Nth letter stands for the digit N. */
const OMOCODIA_LETTERS = 'LMNPQRSTUV'

const MONTH_LETTERS = 'ABCDEHLMPRST'

/** A female birth day is stored with 40 added to it. */
const FEMALE_DAY_OFFSET = 40

const VOWELS = 'AEIOU'

const TRIPLE_LENGTH = 3

const TRIPLE_FILLER = 'X'

/** Values of each character when it sits at an odd (1-based) position. */
const ODD_VALUES: Record<string, number> = {
  '0': 1, '1': 0, '2': 5, '3': 7, '4': 9,
  '5': 13, '6': 15, '7': 17, '8': 19, '9': 21,
  A: 1, B: 0, C: 5, D: 7, E: 9,
  F: 13, G: 15, H: 17, I: 19, J: 21,
  K: 2, L: 4, M: 18, N: 20, O: 11,
  P: 3, Q: 6, R: 8, S: 12, T: 14,
  U: 16, V: 10, W: 22, X: 25, Y: 24, Z: 23,
}

export interface EncodedBirthDate {
  /** Two-digit year: the century is NOT encoded in the code. */
  year: number
  month: number
  day: number
}

/** Uppercase, stripped of every separator the user may have typed. */
export function normalizeTaxCode(value: string): string {
  return value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '')
}

/** Structurally well formed AND carrying the right control character. */
export function isValidTaxCode(value: string): boolean {
  const code = normalizeTaxCode(value)

  if (!TAX_CODE_PATTERN.test(code)) {
    return false
  }

  return controlCharacter(code) === code[15]
}

/** Restores the digits an omocodia-corrected code hides behind letters. */
export function withoutOmocodia(code: string): string {
  const characters = code.split('')

  for (const position of OMOCODIA_POSITIONS) {
    const digit = OMOCODIA_LETTERS.indexOf(characters[position])

    if (digit !== -1) {
      characters[position] = String(digit)
    }
  }

  return characters.join('')
}

/** The birth date the code encodes, or null when the encoded day is out of range. */
export function taxCodeBirthDate(value: string): EncodedBirthDate | null {
  const plain = withoutOmocodia(normalizeTaxCode(value))
  const month = MONTH_LETTERS.indexOf(plain[8])

  if (month === -1) {
    return null
  }

  const encodedDay = Number(plain.slice(9, 11))
  const day = encodedDay > FEMALE_DAY_OFFSET ? encodedDay - FEMALE_DAY_OFFSET : encodedDay

  if (day < 1 || day > 31) {
    return null
  }

  return { year: Number(plain.slice(6, 8)), month: month + 1, day }
}

/** True when the code encodes a female birth day (day + 40). */
export function isFemaleTaxCode(value: string): boolean {
  const plain = withoutOmocodia(normalizeTaxCode(value))

  return Number(plain.slice(9, 11)) > FEMALE_DAY_OFFSET
}

/** The surname triple the code carries (first three characters). */
export function taxCodeSurnameTriple(value: string): string {
  return normalizeTaxCode(value).slice(0, 3)
}

/** The given-name triple the code carries (characters 4 to 6). */
export function taxCodeNameTriple(value: string): string {
  return normalizeTaxCode(value).slice(3, 6)
}

/** Consonants first, then vowels, padded with X. */
export function encodeSurname(surname: string): string {
  const { consonants, vowels } = splitLetters(surname)

  return toTriple(consonants + vowels)
}

/** Same rule as the surname, except a name with 4+ consonants drops the second. */
export function encodeName(name: string): string {
  const { consonants, vowels } = splitLetters(name)

  if (consonants.length >= 4) {
    return consonants[0] + consonants[2] + consonants[3]
  }

  return toTriple(consonants + vowels)
}

function controlCharacter(code: string): string {
  let sum = 0

  for (let position = 0; position < 15; position++) {
    const character = code[position]

    // Positions are weighted by their 1-based parity: index 0 is odd.
    sum += position % 2 === 0 ? ODD_VALUES[character] : evenValue(character)
  }

  return String.fromCharCode('A'.charCodeAt(0) + (sum % 26))
}

/** At an even (1-based) position a digit is worth itself and a letter its alphabet index. */
function evenValue(character: string): number {
  return /\d/.test(character)
    ? Number(character)
    : character.charCodeAt(0) - 'A'.charCodeAt(0)
}

function splitLetters(value: string): { consonants: string; vowels: string } {
  let consonants = ''
  let vowels = ''

  for (const letter of onlyLetters(value)) {
    if (VOWELS.includes(letter)) {
      vowels += letter
    } else {
      consonants += letter
    }
  }

  return { consonants, vowels }
}

function toTriple(letters: string): string {
  return letters.padEnd(TRIPLE_LENGTH, TRIPLE_FILLER).slice(0, TRIPLE_LENGTH)
}

/** Uppercase A-Z only: spaces, apostrophes and diacritics carry no code. */
function onlyLetters(value: string): string {
  return value
    .trim()
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Z]/g, '')
}
