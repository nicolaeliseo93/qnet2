import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { EaSectorForm } from '@/features/ea-sectors/ea-sector-form'
import type { EaSectorDetailWithPermissions } from '@/features/ea-sectors/types'
import type { EaSectorTreeNode } from '@/features/ea-sectors/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0018 AC-017/AC-018: metadata-driven form fields, parent picker anti-cycle exclusion. */

const createEaSectorMock = vi.fn()
const updateEaSectorMock = vi.fn()
const fetchEaSectorTreeMock = vi.fn<() => Promise<EaSectorTreeNode[]>>()

vi.mock('@/features/ea-sectors/api', () => ({
  createEaSector: (...args: unknown[]) => createEaSectorMock(...args),
  updateEaSector: (...args: unknown[]) => updateEaSectorMock(...args),
  fetchEaSectorTree: () => fetchEaSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata: every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/ea-sectors/use-ea-sector-form-meta', () => ({
  useEaSectorFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

/** Root A > Child A1 > Grandchild A1a, plus a second root sibling. */
const TREE: EaSectorTreeNode[] = [
  {
    id: 1,
    name: 'Root A',
    parent_id: null,
    children: [
      {
        id: 2,
        name: 'Child A1',
        parent_id: 1,
        children: [{ id: 3, name: 'Grandchild A1a', parent_id: 2, children: [] }],
      },
    ],
  },
  { id: 4, name: 'Root B', parent_id: null, children: [] },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function sector(
  overrides: Partial<EaSectorDetailWithPermissions> = {},
): EaSectorDetailWithPermissions {
  return {
    id: 2,
    name: 'Child A1',
    parent_id: 1,
    parent: { id: 1, name: 'Root A' },
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

function openParentPicker() {
  fireEvent.click(screen.getByRole('combobox'))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createEaSectorMock.mockReset()
  updateEaSectorMock.mockReset()
  fetchEaSectorTreeMock.mockReset()
  fetchEaSectorTreeMock.mockResolvedValue(TREE)
})

describe('EaSectorForm — create/edit (AC-017)', () => {
  it('renders the name field and the parent picker in create mode', async () => {
    render(
      <EaSectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('combobox')).toBeInTheDocument()

    openParentPicker()
    expect(await screen.findByRole('option', { name: 'No parent (root sector)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root B' })).toBeInTheDocument()
  })

  it('shows a validation error when name is empty on submit', async () => {
    render(
      <EaSectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Name is required.')).toBeInTheDocument()
    expect(createEaSectorMock).not.toHaveBeenCalled()
  })

  it('shows a validation error when name exceeds 191 characters', async () => {
    render(
      <EaSectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'a'.repeat(192) } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Name must be at most 191 characters.')).toBeInTheDocument()
    expect(createEaSectorMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with parent_id null as root', async () => {
    createEaSectorMock.mockResolvedValue(sector({ id: 5, name: 'New Sector', parent_id: null, parent: null }))
    const onSuccess = vi.fn()

    render(
      <EaSectorForm mode={{ type: 'create', parentId: null }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'New Sector' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createEaSectorMock).toHaveBeenCalledTimes(1))
    expect(createEaSectorMock).toHaveBeenCalledWith({
      name: 'New Sector',
      parent_id: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('hydrates name and parent in edit mode', () => {
    render(
      <EaSectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Child A1')
  })
})

describe('EaSectorForm — parent picker anti-cycle exclusion (AC-018)', () => {
  it('excludes the edited node and its subtree from the parent options', async () => {
    render(
      <EaSectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    openParentPicker()

    expect(await screen.findByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root B' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Child A1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Grandchild A1a/ })).not.toBeInTheDocument()
  })

  it('submits only the changed field on a partial update', async () => {
    updateEaSectorMock.mockResolvedValue(sector({ name: 'Renamed' }))

    render(
      <EaSectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateEaSectorMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateEaSectorMock.mock.calls[0]
    expect(id).toBe(2)
    expect(payload).toEqual({ name: 'Renamed' })
  })
})
