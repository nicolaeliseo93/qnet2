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

// Replace the async single-select responsabile with a lightweight controllable
// stub so this suite focuses on the form's own logic, not the network-backed
// select (covered by its own component test).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
  }: {
    value: number | null
    onChange: (value: number | null) => void
  }) => (
    <div>
      <span data-testid="manager-value">{value ?? ''}</span>
      <button type="button" onClick={() => onChange(5)}>
        select-manager-5
      </button>
      <button type="button" onClick={() => onChange(null)}>
        clear-manager
      </button>
    </div>
  ),
}))

// Same for the associated-users multiselect.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
  }: {
    value: number[]
    onChange: (value: number[]) => void
  }) => (
    <div>
      <span data-testid="users-value">{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, 7])}>
        add-user-7
      </button>
    </div>
  ),
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
    created_at: null,
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
  it('AC-017 — renders name, type, responsabile and associated-users fields in create mode', () => {
    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Type' })).toBeInTheDocument()
    expect(screen.getByTestId('users-value')).toBeInTheDocument()
    expect(screen.getByText('select-manager-5')).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    const onSuccess = vi.fn()

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    fireEvent.click(screen.getByText('select-manager-5'))
    fireEvent.click(screen.getByText('add-user-7'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock).toHaveBeenCalledWith({
      name: 'Support',
      type: null,
      manager_id: 5,
      users: [7],
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

  it('hydrates responsabile and associated users in edit mode', () => {
    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('manager-value')).toHaveTextContent('5')
    expect(screen.getByTestId('users-value')).toHaveTextContent('11')
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
