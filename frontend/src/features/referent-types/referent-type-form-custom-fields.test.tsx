import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the referent-types module wires the universal custom-fields
 * renderer (mirrors `company-form-custom-fields.test.tsx`) — mounting
 * `<CustomFieldsSection>` is the only referent-types-specific integration.
 */

const createReferentTypeMock = vi.fn()
const updateReferentTypeMock = vi.fn()

vi.mock('@/features/referent-types/api', () => ({
  createReferentType: (...args: unknown[]) => createReferentTypeMock(...args),
  updateReferentType: (...args: unknown[]) => updateReferentTypeMock(...args),
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

function referentType(
  overrides: Partial<ReferentTypeDetailWithPermissions> = {},
): ReferentTypeDetailWithPermissions {
  return {
    id: 9,
    name: 'Sponsor',
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createReferentTypeMock.mockReset()
  updateReferentTypeMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('ReferentTypeForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <ReferentTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createReferentTypeMock.mockResolvedValue(referentType())
    const onSuccess = vi.fn()

    render(
      <ReferentTypeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key referent type' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createReferentTypeMock).toHaveBeenCalledTimes(1))
    const payload = createReferentTypeMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key referent type' })
  })

  it('seeds the custom field value from the loaded referent-type detail in edit mode', async () => {
    render(
      <ReferentTypeForm
        mode={{
          type: 'edit',
          referentType: referentType({ custom_fields: { notes: 'Existing note' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })
})
