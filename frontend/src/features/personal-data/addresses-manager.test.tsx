import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import type { AddressDraft } from '@/features/personal-data/types'

/**
 * AddressesManager consumes `useConfirm` (dialog); wrap in a QueryClient too so
 * any config-backed lookup resolves, mirroring the app's root providers.
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
// without pulling in the GeoSelect/config the real form needs. The stub submits a
// non-primary address.
vi.mock('@/features/personal-data/address-form', () => ({
  AddressForm: ({
    onSubmit,
  }: {
    onSubmit: (fields: Omit<AddressDraft, '_key'>) => void
  }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() =>
        onSubmit({
          line1: 'New Street',
          line2: null,
          postal_code: null,
          city_id: null,
          province_id: null,
          state_id: null,
          country_id: null,
          is_primary: false,
        })
      }
    >
      stub-save
    </button>
  ),
}))

function address(overrides: Partial<AddressDraft> = {}): AddressDraft {
  return {
    _key: 'address-1',
    id: 1,
    line1: '10 Downing Street',
    line2: null,
    postal_code: 'SW1A 2AA',
    city_id: null,
    province_id: null,
    state_id: null,
    country_id: null,
    is_primary: false,
    ...overrides,
  }
}

describe('AddressesManager (controlled)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the empty state when there are no addresses', () => {
    renderWithConfirm(<AddressesManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No addresses yet.')).toBeInTheDocument()
  })

  it('renders each address line and its secondary/postal summary', () => {
    renderWithConfirm(
      <AddressesManager value={[address({ line2: 'Flat 2' })]} onChange={() => {}} />,
    )
    expect(screen.getByText('10 Downing Street')).toBeInTheDocument()
    expect(screen.getByText('Flat 2 · SW1A 2AA')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Delete address' }),
    ).toBeInTheDocument()
  })

  it('removes an address from the buffer without any network call', async () => {
    const onChange = vi.fn()

    renderWithConfirm(<AddressesManager value={[address()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete address' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete address' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
    expect(onChange).toHaveBeenCalledTimes(1)
  })

  it('enforces a single primary across the buffer (first becomes default)', async () => {
    const onChange = vi.fn()

    const primary = address({ _key: 'address-1', id: 1, is_primary: true })
    const other = address({ _key: 'address-2', id: 2, is_primary: false })

    renderWithConfirm(<AddressesManager value={[primary, other]} onChange={onChange} />)
    // Delete the current primary; the remaining address must be promoted.
    fireEvent.click(
      screen.getAllByRole('button', { name: 'Delete address' })[0],
    )
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete address' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledTimes(1))
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next).toHaveLength(1)
    expect(next[0]._key).toBe('address-2')
    expect(next[0].is_primary).toBe(true)
  })

  it('makes the first added address primary by default', () => {
    const onChange = vi.fn()

    renderWithConfirm(<AddressesManager value={[]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add address' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next).toHaveLength(1)
    expect(next[0].line1).toBe('New Street')
    // No address was primary, so the first one becomes the default.
    expect(next[0].is_primary).toBe(true)
    expect(next[0].id).toBeUndefined()
  })
})
