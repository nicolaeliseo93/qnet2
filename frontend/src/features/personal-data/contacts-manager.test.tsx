import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import type { ContactDraft } from '@/features/personal-data/types'

/**
 * ContactsManager consumes `useConfirm` (dialog) and `useEnumOptions` (config
 * query), so every render needs both providers — the same the app mounts.
 */
function renderWithConfirm(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

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
    renderWithConfirm(<ContactsManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No contacts yet.')).toBeInTheDocument()
  })

  it('renders each contact value, its label and the primary badge', () => {
    renderWithConfirm(<ContactsManager value={[contact()]} onChange={() => {}} />)
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
    expect(screen.getByText('Work')).toBeInTheDocument()
    expect(screen.getByText('Primary')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Edit contact' }),
    ).toBeInTheDocument()
  })

  it('removes a contact from the buffer without any network call', async () => {
    const onChange = vi.fn()

    renderWithConfirm(<ContactsManager value={[contact()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete contact' }))

    // Confirm through the reusable dialog (scoped so it isn't the row's button).
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete contact' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
    expect(onChange).toHaveBeenCalledTimes(1)
  })

  it('appends a new contact and demotes the previous primary of that type', () => {
    const onChange = vi.fn()
    const existing = contact({ _key: 'contact-1', id: 1, is_primary: true })

    renderWithConfirm(<ContactsManager value={[existing]} onChange={onChange} />)
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
