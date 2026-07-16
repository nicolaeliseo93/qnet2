import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criterion AC-018 (spec 0010): the metadata-driven behaviour of
 * the form (hidden field absent, readonly field not editable, required field
 * marked, server 422 mapped inline). The business-function CRUD behaviour
 * itself (payload shaping, hydration, …) is covered by
 * `business-function-form.test.tsx`.
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

// Keyed by `resource` so the responsabile and parent-function single-selects
// (both `AsyncPaginatedSelect`) stay independently addressable; same for the
// associated-users and operational-sites multiselects.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ resource, value }: { resource: string; value: number | null }) => (
    <div data-testid={`${resource}-value`}>{value ?? ''}</div>
  ),
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ resource, value }: { resource: string; value: number[] }) => (
    <div data-testid={`${resource}-values`}>{value.join(',')}</div>
  ),
}))

/** The `<label>` element whose text starts with `text` (exact-match helper). */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
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
    manager_id: 5,
    manager: { id: 5, name: 'Ada Lovelace', avatar_url: null },
    user_ids: [],
    users: [],
    parent_id: null,
    parent: null,
    operational_site_ids: [],
    operational_sites: [],
    created_at: '2026-01-01T00:00:00Z',
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
  createBusinessFunctionMock.mockReset()
  updateBusinessFunctionMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('BusinessFunctionForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          name: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          type: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          manager_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
          users: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The hidden field is absent from the DOM.
    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    expect(screen.queryByRole('combobox', { name: 'Type' })).not.toBeInTheDocument()

    // `required` from metadata drives the label's `*` — name is required,
    // the (visible) responsabile field is not.
    expect(labelFor('Name').textContent).toContain('*')
    expect(labelFor('Responsible').textContent).not.toContain('*')
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <BusinessFunctionForm
        mode={{
          type: 'edit',
          businessFunction: businessFunction({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                name: {
                  visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
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
  })

  it('falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          // `manager_id` intentionally absent from the metadata.
          name: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <BusinessFunctionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    // No crash, and the responsabile field renders (the graceful default).
    expect(screen.getByTestId('users-value')).toBeInTheDocument()
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateBusinessFunctionMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { name: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <BusinessFunctionForm
        mode={{ type: 'edit', businessFunction: businessFunction() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateBusinessFunctionMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
