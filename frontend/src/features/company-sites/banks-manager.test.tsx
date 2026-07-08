import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { BanksManager } from '@/features/company-sites/banks-manager'
import type { BankDraft } from '@/features/company-sites/types'

/** Spec 0020 AC-018: buffered add/edit/remove, no per-row network call. */

function renderWithConfirm(ui: ReactElement) {
  return render(<ConfirmDialogProvider>{ui}</ConfirmDialogProvider>)
}

// Stub the inline dialog form so the manager's buffer logic is tested in
// isolation, mirroring `contacts-manager.test.tsx`'s stub of `ContactForm`.
vi.mock('@/features/company-sites/bank-form', () => ({
  BankForm: ({
    onSubmit,
  }: {
    onSubmit: (fields: Omit<BankDraft, '_key'>) => void
  }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() =>
        onSubmit({ name: 'Nuova Banca', iban: 'IT60X0542811101000000123456', notes: null, is_primary: true })
      }
    >
      stub-save
    </button>
  ),
}))

function bank(overrides: Partial<BankDraft> = {}): BankDraft {
  return {
    _key: 'bank-1',
    id: 1,
    name: 'Banca Test',
    iban: 'IT60X0542811101000000123456',
    notes: null,
    is_primary: false,
    ...overrides,
  }
}

describe('BanksManager (controlled)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the empty state when there are no banks', () => {
    renderWithConfirm(<BanksManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No banks yet.')).toBeInTheDocument()
  })

  it('renders each bank name and IBAN', () => {
    renderWithConfirm(<BanksManager value={[bank()]} onChange={() => {}} />)
    expect(screen.getByText('Banca Test')).toBeInTheDocument()
    expect(screen.getByText('IT60X0542811101000000123456')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit bank' })).toBeInTheDocument()
  })

  it('appends a new bank to the buffer without any network call', () => {
    const onChange = vi.fn()

    renderWithConfirm(<BanksManager value={[bank()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add bank' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as BankDraft[]
    expect(next).toHaveLength(2)
    expect(next[1].name).toBe('Nuova Banca')
    expect(next[1].id).toBeUndefined()
  })

  it('removes a bank from the buffer after confirming', async () => {
    const onChange = vi.fn()

    renderWithConfirm(<BanksManager value={[bank()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete bank' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete bank' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
  })

  it('shows the preferred badge on the primary bank', () => {
    renderWithConfirm(<BanksManager value={[bank({ is_primary: true })]} onChange={() => {}} />)
    expect(screen.getByText('Preferred')).toBeInTheDocument()
  })

  it('keeps at most one preferred bank: adding a preferred one demotes the others', () => {
    const onChange = vi.fn()

    renderWithConfirm(<BanksManager value={[bank({ is_primary: true })]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add bank' }))
    // The stub submits a bank with is_primary: true.
    fireEvent.click(screen.getByTestId('stub-submit'))

    const next = onChange.mock.calls[0][0] as BankDraft[]
    expect(next).toHaveLength(2)
    expect(next[0].is_primary).toBe(false)
    expect(next[1].is_primary).toBe(true)
  })

  it('does not render add/edit/remove affordances when read-only', () => {
    renderWithConfirm(<BanksManager value={[bank()]} onChange={() => {}} readOnly />)
    expect(screen.queryByRole('button', { name: 'Add bank' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit bank' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete bank' })).not.toBeInTheDocument()
  })
})
