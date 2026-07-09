import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SourceForm } from '@/features/sources/source-form'
import type { SourceDetailWithPermissions } from '@/features/sources/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the sources module wires the universal custom-fields renderer
 * (mirrors `company-form-custom-fields.test.tsx`) — mounting
 * `<CustomFieldsSection>` is the only sources-specific integration.
 */

const createSourceMock = vi.fn()
const updateSourceMock = vi.fn()

vi.mock('@/features/sources/api', () => ({
  createSource: (...args: unknown[]) => createSourceMock(...args),
  updateSource: (...args: unknown[]) => updateSourceMock(...args),
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

function source(overrides: Partial<SourceDetailWithPermissions> = {}): SourceDetailWithPermissions {
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
  createSourceMock.mockReset()
  updateSourceMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('SourceForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(<SourceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createSourceMock.mockResolvedValue(source())
    const onSuccess = vi.fn()

    render(<SourceForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key sponsor' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createSourceMock).toHaveBeenCalledTimes(1))
    const payload = createSourceMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key sponsor' })
  })

  it('seeds the custom field value from the loaded source detail in edit mode', async () => {
    render(
      <SourceForm
        mode={{ type: 'edit', source: source({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })
})
