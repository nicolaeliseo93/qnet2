import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { customFields as enCustomFields } from '@/i18n/locales/en-custom-fields'
import type { FieldPermission, ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor, CustomFieldsFormShape } from '@/features/custom-fields/types'

/** Spec 0021 AC-022: CustomFieldsSection renders the right control per type, gated by MetaField. */

const fetchResourceMetaMock = vi.fn()
const fetchForSelectMock = vi.fn()

vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: (resource: string) => fetchResourceMetaMock(resource),
}))

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { customFields: enCustomFields }, true, true)
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
  })
})

function permission(overrides: Partial<FieldPermission> = {}): FieldPermission {
  return {
    visible: true,
    hidden: false,
    editable: true,
    readonly: false,
    required: false,
    disabled: false,
    ...overrides,
  }
}

function meta(fields: CustomFieldDescriptor[], fieldPermissions: Record<string, FieldPermission>): ResourceMeta {
  const permissions: ResourcePermissions = {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: fieldPermissions,
    actions: {},
  }
  return { fields, permissions }
}

const TEXT_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: 'Details',
  mandatory: false,
  source: 'custom',
}

const INTEGER_FIELD: CustomFieldDescriptor = {
  key: 'custom.headcount',
  type: 'integer',
  label: 'Headcount',
  group: 'Details',
  mandatory: false,
  source: 'custom',
}

const BOOLEAN_FIELD: CustomFieldDescriptor = {
  key: 'custom.active',
  type: 'boolean',
  label: 'Active',
  group: null,
  mandatory: false,
  source: 'custom',
  config: { display: 'checkbox' },
}

const ENUM_FIELD: CustomFieldDescriptor = {
  key: 'custom.tier',
  type: 'enum',
  label: 'Tier',
  group: null,
  mandatory: false,
  source: 'custom',
  config: { display: 'select' },
  options: [
    { value: 'gold', label: 'Gold' },
    { value: 'silver', label: 'Silver' },
  ],
}

const RELATION_FIELD: CustomFieldDescriptor = {
  key: 'custom.owner',
  type: 'relation',
  label: 'Owner',
  group: null,
  mandatory: false,
  source: 'custom',
  relation: { for_select_resource: 'users', cardinality: 'one' },
}

const DATE_FIELD: CustomFieldDescriptor = {
  key: 'custom.expires_at',
  type: 'date',
  label: 'Expires at',
  group: null,
  mandatory: false,
  source: 'custom',
}

const EMAIL_FIELD: CustomFieldDescriptor = {
  key: 'custom.contact_email',
  type: 'email',
  label: 'Contact email',
  group: null,
  mandatory: false,
  source: 'custom',
}

const COLOR_FIELD: CustomFieldDescriptor = {
  key: 'custom.brand_color',
  type: 'color',
  label: 'Brand color',
  group: null,
  mandatory: false,
  source: 'custom',
}

function Harness({ resource, permissions }: { resource: string; permissions: ResourcePermissions }) {
  const form = useForm<CustomFieldsFormShape>({ defaultValues: { custom_fields: {} } })
  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <Form {...form}>
        <form>
          <CustomFieldsSection resource={resource} control={form.control} />
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}

function renderHarness(resource: string, permissions: ResourcePermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness resource={resource} permissions={permissions} />
    </QueryClientProvider>,
  )
}

describe('CustomFieldsSection', () => {
  it('renders one control per type: text, integer, boolean, enum, relation', async () => {
    const data = meta([TEXT_FIELD, INTEGER_FIELD, BOOLEAN_FIELD, ENUM_FIELD, RELATION_FIELD], {
      'custom.notes': permission(),
      'custom.headcount': permission(),
      'custom.active': permission(),
      'custom.tier': permission(),
      'custom.owner': permission(),
    })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Headcount' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Active' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tier' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Owner' })).toBeInTheDocument()
  })

  it('renders native inputs for the string-backed scalar types (date/email/color)', async () => {
    const data = meta([DATE_FIELD, EMAIL_FIELD, COLOR_FIELD], {
      'custom.expires_at': permission(),
      'custom.contact_email': permission(),
      'custom.brand_color': permission(),
    })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    // date/color inputs expose no ARIA role, so they are found by label; email
    // is a textbox. Each carries the matching native HTML input `type`.
    expect(await screen.findByLabelText('Expires at')).toHaveAttribute('type', 'date')
    expect(screen.getByRole('textbox', { name: 'Contact email' })).toHaveAttribute('type', 'email')
    expect(screen.getByLabelText('Brand color')).toHaveAttribute('type', 'color')
  })

  it('does not render a field the role cannot see', async () => {
    const data = meta([TEXT_FIELD, INTEGER_FIELD], {
      'custom.notes': permission(),
      'custom.headcount': permission({ visible: false, hidden: true }),
    })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
    expect(screen.queryByRole('spinbutton', { name: 'Headcount' })).not.toBeInTheDocument()
  })

  it('applies readonly/required from the field permission', async () => {
    const data = meta([TEXT_FIELD], {
      'custom.notes': permission({ editable: false, readonly: true, required: true }),
    })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    expect(notes).toHaveAttribute('readonly')
    expect(notes).toBeDisabled()
    expect(screen.getByText('*')).toBeInTheDocument()
  })

  it('renders enum options for a select-display field', async () => {
    const data = meta([ENUM_FIELD], { 'custom.tier': permission() })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    expect(await screen.findByRole('combobox', { name: 'Tier' })).toBeInTheDocument()
  })

  it('groups ungrouped custom fields under the "Other fields" section heading', async () => {
    const data = meta([BOOLEAN_FIELD, ENUM_FIELD], {
      'custom.active': permission(),
      'custom.tier': permission(),
    })
    fetchResourceMetaMock.mockResolvedValue(data)

    renderHarness('companies', data.permissions)

    expect(await screen.findByRole('heading', { name: 'Other fields' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Active' })).toBeInTheDocument()
  })

  it('renders nothing for a resource with no custom fields', async () => {
    const data = meta([], {})
    fetchResourceMetaMock.mockResolvedValue(data)

    const { container } = renderHarness('companies', data.permissions)
    await waitFor(() => expect(fetchResourceMetaMock).toHaveBeenCalled())

    expect(container.querySelector('input, select, button')).not.toBeInTheDocument()
  })
})
