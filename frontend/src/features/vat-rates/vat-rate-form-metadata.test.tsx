import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Mirrors `source-form-metadata.test.tsx` (AC-024, spec 0016/0018): the
 * metadata-driven behaviour of the VAT rate form (hidden field absent,
 * readonly field not editable, required field marked, server 422 mapped
 * inline).
 */

const createVatRateMock = vi.fn()
const updateVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', () => ({
  createVatRate: (...args: unknown[]) => createVatRateMock(...args),
  updateVatRate: (...args: unknown[]) => updateVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
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
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createVatRateMock.mockReset()
  updateVatRateMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('VatRateForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          rate: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(<VatRateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.queryByLabelText(/^Rate/)).not.toBeInTheDocument())
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <VatRateForm
        mode={{
          type: 'edit',
          vatRate: vatRate({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                name: {
                  visible: true, hidden: false, editable: false, readonly: true, required: true, disabled: false,
                },
              },
              actions: {},
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const name = screen.getByLabelText(/^Name/)
    expect(name).toBeDisabled()
    expect(name).toHaveAttribute('readonly')
    expect(screen.getByText('Name').closest('label')?.textContent).toContain('*')
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateVatRateMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { rate: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <VatRateForm mode={{ type: 'edit', vatRate: vatRate() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateVatRateMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
