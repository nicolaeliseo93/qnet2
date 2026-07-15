import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PipelineStatusForm } from '@/features/pipeline-statuses/pipeline-status-form'
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createPipelineStatusMock = vi.fn()
const updatePipelineStatusMock = vi.fn()

vi.mock('@/features/pipeline-statuses/api', () => ({
  createPipelineStatus: (...args: unknown[]) => createPipelineStatusMock(...args),
  updatePipelineStatus: (...args: unknown[]) => updatePipelineStatusMock(...args),
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

vi.mock('@/features/pipeline-statuses/use-pipeline-status-form-meta', () => ({
  usePipelineStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `usePipelineStatusForm` reads `/meta/pipeline-statuses` (spec 0021) to build
// the dynamic custom-fields schema; this suite has no custom fields to
// exercise, so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function pipelineStatus(
  overrides: Partial<PipelineStatusDetailWithPermissions> = {},
): PipelineStatusDetailWithPermissions {
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
  createPipelineStatusMock.mockReset()
  updatePipelineStatusMock.mockReset()
})

describe('PipelineStatusForm — create/edit (spec 0023)', () => {
  it('renders the name, color and sort order fields in create mode', () => {
    render(
      <PipelineStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByLabelText(/^Order/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createPipelineStatusMock.mockResolvedValue(pipelineStatus())
    const onSuccess = vi.fn()

    render(
      <PipelineStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Draft' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createPipelineStatusMock).toHaveBeenCalledTimes(1))
    expect(createPipelineStatusMock).toHaveBeenCalledWith({ name: 'Draft', color: null, sort_order: 0 })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(pipelineStatus()))
  })

  it('hydrates name, color and sort order in edit mode', () => {
    render(
      <PipelineStatusForm
        mode={{ type: 'edit', pipelineStatus: pipelineStatus() }}
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
    updatePipelineStatusMock.mockResolvedValue(pipelineStatus({ name: 'Active' }))

    render(
      <PipelineStatusForm
        mode={{ type: 'edit', pipelineStatus: pipelineStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Active' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updatePipelineStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updatePipelineStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Active' })
  })
})
