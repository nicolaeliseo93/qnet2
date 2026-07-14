import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadStatusForm } from '@/features/lead-statuses/lead-status-form'
import type { LeadStatusDetailWithPermissions } from '@/features/lead-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createLeadStatusMock = vi.fn()
const updateLeadStatusMock = vi.fn()

vi.mock('@/features/lead-statuses/api', () => ({
  createLeadStatus: (...args: unknown[]) => createLeadStatusMock(...args),
  updateLeadStatus: (...args: unknown[]) => updateLeadStatusMock(...args),
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

vi.mock('@/features/lead-statuses/use-lead-status-form-meta', () => ({
  useLeadStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useLeadStatusForm` reads `/meta/lead-statuses` (spec 0021) to build the
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

function leadStatus(
  overrides: Partial<LeadStatusDetailWithPermissions> = {},
): LeadStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Draft',
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
  createLeadStatusMock.mockReset()
  updateLeadStatusMock.mockReset()
})

describe('LeadStatusForm — create/edit (spec 0029)', () => {
  it('renders the name, color and sort order fields in create mode', () => {
    render(
      <LeadStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByLabelText(/^Order/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createLeadStatusMock.mockResolvedValue(leadStatus())
    const onSuccess = vi.fn()

    render(
      <LeadStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Draft' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadStatusMock).toHaveBeenCalledTimes(1))
    expect(createLeadStatusMock).toHaveBeenCalledWith({ name: 'Draft', color: null, sort_order: 0 })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(leadStatus()))
  })

  it('hydrates name, color and sort order in edit mode', () => {
    render(
      <LeadStatusForm
        mode={{ type: 'edit', leadStatus: leadStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Draft')
    expect(screen.getByLabelText(/^Order/)).toHaveValue(1)
    expect(screen.getByRole('button', { name: /Blue/ })).toBeInTheDocument()
  })

  it('submits only the changed name on a partial update', async () => {
    updateLeadStatusMock.mockResolvedValue(leadStatus({ name: 'Active' }))

    render(
      <LeadStatusForm
        mode={{ type: 'edit', leadStatus: leadStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Active' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateLeadStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateLeadStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Active' })
  })
})
