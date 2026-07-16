import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ReferentDuplicateWarning } from '@/features/referents/referent-duplicate-warning'
import type { ReferentDuplicateMatch } from '@/features/referents/duplicate-check-api'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ReferentDuplicateWarning (AC-007, AC-009)', () => {
  it('renders nothing without matches (AC-008)', () => {
    const { container } = render(<ReferentDuplicateWarning matches={[]} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('announces the matched referent and its criteria under role="status"', () => {
    const matches: ReferentDuplicateMatch[] = [
      { referent_id: 1, name: 'Mario Rossi', matched_on: ['email', 'tax_code'] },
    ]
    render(<ReferentDuplicateWarning matches={matches} />)

    const status = screen.getByRole('status')
    expect(status).toHaveTextContent('Mario Rossi might be a duplicate (email, tax code).')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('lists every match once, in the order returned by the API', () => {
    const matches: ReferentDuplicateMatch[] = [
      { referent_id: 1, name: 'Mario Rossi', matched_on: ['email'] },
      { referent_id: 2, name: 'Anna Bianchi', matched_on: ['phone'] },
    ]
    render(<ReferentDuplicateWarning matches={matches} />)

    expect(screen.getByText(/Mario Rossi/)).toBeInTheDocument()
    expect(screen.getByText(/Anna Bianchi/)).toBeInTheDocument()
  })
})
