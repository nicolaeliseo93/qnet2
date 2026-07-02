import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import type { ContactDraft } from '@/features/personal-data/types'

// Stub the inline editor so the manager's buffer logic is tested in isolation,
// without pulling in the config (enum options) the real form needs. The stub
// submits a fixed primary email contact.
vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: ({
    onSubmit,
  }: {
    onSubmit: (fields: Omit<ContactDraft, '_key'>) => void
  }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() =>
        onSubmit({ type: 'email', value: 'new@example.com', label: null, is_primary: true })
      }
    >
      stub-save
    </button>
  ),
}))

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'contact-1',
    id: 1,
    type: 'email',
    label: 'Work',
    value: 'ada@example.com',
    is_primary: true,
    ...overrides,
  }
}

describe('ContactsManager (controlled)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the empty state when there are no contacts', () => {
    render(<ContactsManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No contacts yet.')).toBeInTheDocument()
  })

  it('renders each contact value, its label and the primary badge', () => {
    render(<ContactsManager value={[contact()]} onChange={() => {}} />)
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
    expect(screen.getByText('Work')).toBeInTheDocument()
    expect(screen.getByText('Primary')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Edit contact' }),
    ).toBeInTheDocument()
  })

  it('removes a contact from the buffer without any network call', () => {
    const onChange = vi.fn()
    vi.spyOn(window, 'confirm').mockReturnValue(true)

    render(<ContactsManager value={[contact()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete contact' }))

    expect(onChange).toHaveBeenCalledTimes(1)
    expect(onChange).toHaveBeenCalledWith([])
  })

  it('appends a new contact and demotes the previous primary of that type', () => {
    const onChange = vi.fn()
    const existing = contact({ _key: 'contact-1', id: 1, is_primary: true })

    render(<ContactsManager value={[existing]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add contact' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as ContactDraft[]
    expect(next).toHaveLength(2)
    // The new email contact is primary; the prior email is demoted (per-type).
    expect(next[0].is_primary).toBe(false)
    expect(next[1].value).toBe('new@example.com')
    expect(next[1].is_primary).toBe(true)
    expect(next[1].id).toBeUndefined()
  })
})
