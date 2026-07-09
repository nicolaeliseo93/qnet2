import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: business-functions is one of the universal custom-fields
 * rollout modules — mounting `<CustomFieldsSection>` is the ONLY
 * business-functions-specific integration. This suite exercises the wiring
 * (schema, defaults, payload, 422) without touching the section's own
 * per-type rendering (covered by `CustomFieldsSection.test.tsx`).
 */

const createBusinessFunctionMock = vi.fn()
const updateBusinessFunctionMock = vi.fn()

vi.mock('@/features/business-functions/api', () => ({
  createBusinessFunction: (...args: unknown[]) => createBusinessFunctionMock(...args),
  updateBusinessFunction: (...args: unknown[]) => updateBusinessFunctionMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: () => <div />,
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

function businessFunction(
  overrides: Partial<BusinessFunctionDetailWithPermissions> = {},
): BusinessFunctionDetailWithPermissions {
  return {
    id: 9,
    name: 'Sales',
    is_business_unit: true,
    is_business_service: false,
    type: 'business_unit',
    manager_id: null,
    manager: null,
    user_ids: [],
    users: [],
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createBusinessFunctionMock.mockReset()
  updateBusinessFunctionMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('BusinessFunctionForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    const onSuccess = vi.fn()

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Sales' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key account' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    const payload = createBusinessFunctionMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key account' })
  })

  it('seeds the custom field value from the loaded business function detail in edit mode', async () => {
    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = businessFunction({ custom_fields: { notes: 'Existing note' } })
    updateBusinessFunctionMock.mockResolvedValue(original)

    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: original }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.change(notes, { target: { value: 'Updated note' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateBusinessFunctionMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { notes: 'Updated note' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateBusinessFunctionMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { 'custom_fields.notes': ['Notes must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes must be shorter.')).toBeInTheDocument())
    expect(updateBusinessFunctionMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
