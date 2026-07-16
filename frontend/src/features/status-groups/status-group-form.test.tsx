import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { StatusGroupForm } from '@/features/status-groups/status-group-form'
import type { StatusGroupDetailWithPermissions } from '@/features/status-groups/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createStatusGroupMock = vi.fn()
const updateStatusGroupMock = vi.fn()

vi.mock('@/features/status-groups/api', () => ({
  createStatusGroup: (...args: unknown[]) => createStatusGroupMock(...args),
  updateStatusGroup: (...args: unknown[]) => updateStatusGroupMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty) — not about authorization metadata.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/status-groups/use-status-group-form-meta', () => ({
  useStatusGroupFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useStatusGroupForm` reads `/meta/status-groups` (spec 0021) to build the
// dynamic custom-fields schema; this suite has no custom fields to exercise,
// so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function statusGroup(
  overrides: Partial<StatusGroupDetailWithPermissions> = {},
): StatusGroupDetailWithPermissions {
  return {
    id: 9,
    name: 'Open',
    color: 'blue',
    sort_order: 1,
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createStatusGroupMock.mockReset()
  updateStatusGroupMock.mockReset()
})

describe('StatusGroupForm — create/edit (spec 0039)', () => {
  it('renders the name, color and sort order fields in create mode', () => {
    render(
      <StatusGroupForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByLabelText(/^Order/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createStatusGroupMock.mockResolvedValue(statusGroup())
    const onSuccess = vi.fn()

    render(
      <StatusGroupForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Open' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createStatusGroupMock).toHaveBeenCalledTimes(1))
    expect(createStatusGroupMock).toHaveBeenCalledWith({ name: 'Open', color: null, sort_order: 0 })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(statusGroup()))
  })

  it('hydrates name, color and sort order in edit mode', () => {
    render(
      <StatusGroupForm
        mode={{ type: 'edit', statusGroup: statusGroup() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Open')
    expect(screen.getByLabelText(/^Order/)).toHaveValue(1)
    expect(screen.getByRole('button', { name: /Blue/ })).toBeInTheDocument()
  })

  it('submits only the changed name on a partial update', async () => {
    updateStatusGroupMock.mockResolvedValue(statusGroup({ name: 'Closed' }))

    render(
      <StatusGroupForm
        mode={{ type: 'edit', statusGroup: statusGroup() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Closed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateStatusGroupMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateStatusGroupMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Closed' })
  })
})
