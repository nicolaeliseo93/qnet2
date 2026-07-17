import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createVatRateMock = vi.fn()
const updateVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', () => ({
  createVatRate: (...args: unknown[]) => createVatRateMock(...args),
  updateVatRate: (...args: unknown[]) => updateVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata (covered by
 * `vat-rate-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/vat-rates/use-vat-rate-form-meta', () => ({
  useVatRateFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useVatRateForm` reads `/meta/vat-rates` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `vat-rate-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function vatRate(overrides: Partial<VatRateDetailWithPermissions> = {}): VatRateDetailWithPermissions {
  return {
    id: 9,
    name: 'Standard',
    rate: 22,
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createVatRateMock.mockReset()
  updateVatRateMock.mockReset()
})

describe('VatRateForm — create/edit', () => {
  it('renders the name and rate fields in create mode', () => {
    render(<VatRateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Rate/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createVatRateMock.mockResolvedValue(vatRate())
    const onSuccess = vi.fn()

    render(<VatRateForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '22' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createVatRateMock).toHaveBeenCalledTimes(1))
    expect(createVatRateMock).toHaveBeenCalledWith({ name: 'Standard', rate: 22 })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(vatRate()))
  })

  it('rejects an empty rate at submit', async () => {
    render(<VatRateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Rate is required.')).toBeInTheDocument())
    expect(createVatRateMock).not.toHaveBeenCalled()
  })

  it('hydrates name and rate in edit mode', () => {
    render(
      <VatRateForm mode={{ type: 'edit', vatRate: vatRate() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Standard')
    expect(screen.getByLabelText(/^Rate/)).toHaveValue(22)
  })

  it('submits only the changed rate on a partial update', async () => {
    updateVatRateMock.mockResolvedValue(vatRate({ rate: 10 }))

    render(
      <VatRateForm mode={{ type: 'edit', vatRate: vatRate() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '10' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateVatRateMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateVatRateMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ rate: 10 })
  })
})
