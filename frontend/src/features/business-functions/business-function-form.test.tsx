import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createBusinessFunctionMock = vi.fn()
const updateBusinessFunctionMock = vi.fn()

vi.mock('@/features/business-functions/api', () => ({
  createBusinessFunction: (...args: unknown[]) => createBusinessFunctionMock(...args),
  updateBusinessFunction: (...args: unknown[]) => updateBusinessFunctionMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `business-function-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty) so
 * create/edit render exactly as they would before spec 0004.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/business-functions/use-business-function-form-meta', () => ({
  useBusinessFunctionFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useBusinessFunctionForm` reads `/meta/business-functions` (spec 0021) to build the
// dynamic custom-fields schema; this suite has no custom fields to exercise, so it
// resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

// Replace the async single-select (responsabile AND parent function both use
// this component) with a lightweight controllable stub, keyed by `resource`
// so the two instances stay independently addressable — this suite focuses
// on the form's own logic, not the network-backed select (covered by its own
// component test).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
  }) => {
    const pickedId = resource === 'users' ? 5 : 50
    return (
      <div>
        <span data-testid={`${resource}-value`}>{value ?? ''}</span>
        <button type="button" onClick={() => onChange(pickedId)}>
          {`select-${resource}-${pickedId}`}
        </button>
        <button type="button" onClick={() => onChange(null)}>
          {`clear-${resource}`}
        </button>
      </div>
    )
  },
}))

// Same for the multiselects (associated users AND operational sites both use
// this component), also keyed by `resource`.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    resource,
    value,
    onChange,
  }: {
    resource: string
    value: number[]
    onChange: (value: number[]) => void
  }) => {
    const addedId = resource === 'users' ? 7 : 70
    return (
      <div>
        <span data-testid={`${resource}-values`}>{value.join(',')}</span>
        <button type="button" onClick={() => onChange([...value, addedId])}>
          {`add-${resource}-${addedId}`}
        </button>
      </div>
    )
  },
}))

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
    manager_id: 5,
    manager: { id: 5, name: 'Ada Lovelace', avatar_url: null },
    user_ids: [11],
    users: [{ id: 11, name: 'Grace Hopper', avatar_url: null }],
    parent_id: 40,
    parent: { id: 40, name: 'Operations' },
    operational_site_ids: [60],
    operational_sites: [{ id: 60, label: 'Via Roma 1 - Milano' }],
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createBusinessFunctionMock.mockReset()
  updateBusinessFunctionMock.mockReset()
})

describe('BusinessFunctionForm — create/edit', () => {
  it('AC-017 — renders name, type, responsabile, associated-users, parent and operational-sites fields in create mode', () => {
    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Type' })).toBeInTheDocument()
    expect(screen.getByTestId('users-values')).toBeInTheDocument()
    expect(screen.getByText('select-users-5')).toBeInTheDocument()
    expect(screen.getByText('select-business-functions-50')).toBeInTheDocument()
    expect(screen.getByTestId('operational-sites-values')).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    const onSuccess = vi.fn()

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    fireEvent.click(screen.getByText('select-users-5'))
    fireEvent.click(screen.getByText('add-users-7'))
    fireEvent.click(screen.getByText('select-business-functions-50'))
    fireEvent.click(screen.getByText('add-operational-sites-70'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock).toHaveBeenCalledWith({
      name: 'Support',
      type: null,
      manager_id: 5,
      users: [7],
      parent_id: 50,
      operational_sites: [70],
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(businessFunction()))
  })

  it('maps the "none" sentinel to type=null and a selected option to its value', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Ops' } })
    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'Business Unit' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock).toHaveBeenCalledWith(
      expect.objectContaining({ type: 'business_unit' }),
    )
  })

  it('hydrates responsabile, associated users, parent and operational sites in edit mode', () => {
    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('users-value')).toHaveTextContent('5')
    expect(screen.getByTestId('users-values')).toHaveTextContent('11')
    expect(screen.getByTestId('business-functions-value')).toHaveTextContent('40')
    expect(screen.getByTestId('operational-sites-values')).toHaveTextContent('60')
  })

  it('submits only the changed fields on a partial update', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction({ name: 'Sales EU' }))

    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sales EU' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateBusinessFunctionMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Sales EU' })
  })
})
