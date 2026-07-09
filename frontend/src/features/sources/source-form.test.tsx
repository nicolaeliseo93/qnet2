import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SourceForm } from '@/features/sources/source-form'
import type { SourceDetailWithPermissions } from '@/features/sources/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createSourceMock = vi.fn()
const updateSourceMock = vi.fn()

vi.mock('@/features/sources/api', () => ({
  createSource: (...args: unknown[]) => createSourceMock(...args),
  updateSource: (...args: unknown[]) => updateSourceMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata (covered by
 * `source-form-metadata.test.tsx`): every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/sources/use-source-form-meta', () => ({
  useSourceFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useSourceForm` reads `/meta/sources` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `source-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

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
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createSourceMock.mockReset()
  updateSourceMock.mockReset()
})

describe('SourceForm — create/edit (AC-024)', () => {
  it('renders the name field in create mode', () => {
    render(<SourceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createSourceMock.mockResolvedValue(source())
    const onSuccess = vi.fn()

    render(<SourceForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createSourceMock).toHaveBeenCalledTimes(1))
    expect(createSourceMock).toHaveBeenCalledWith({ name: 'Sponsor' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(source()))
  })

  it('hydrates the name in edit mode', () => {
    render(
      <SourceForm mode={{ type: 'edit', source: source() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Sponsor')
  })

  it('submits only the changed name on a partial update', async () => {
    updateSourceMock.mockResolvedValue(source({ name: 'Partner' }))

    render(
      <SourceForm mode={{ type: 'edit', source: source() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Partner' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateSourceMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateSourceMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Partner' })
  })
})
