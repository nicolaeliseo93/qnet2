import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the vat-rates module wires the universal custom-fields renderer
 * (mirrors `source-form-custom-fields.test.tsx`) — mounting
 * `<CustomFieldsSection>` is the only vat-rates-specific integration.
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

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.notes': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
  }
}

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
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('VatRateForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(<VatRateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createVatRateMock.mockResolvedValue(vatRate())
    const onSuccess = vi.fn()

    render(<VatRateForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Standard' } })
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '22' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key rate' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createVatRateMock).toHaveBeenCalledTimes(1))
    const payload = createVatRateMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key rate' })
  })

  it('seeds the custom field value from the loaded VAT rate detail in edit mode', async () => {
    render(
      <VatRateForm
        mode={{ type: 'edit', vatRate: vatRate({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })
})
