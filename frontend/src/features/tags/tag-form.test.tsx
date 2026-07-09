import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TagForm } from '@/features/tags/tag-form'
import type { TagDetailWithPermissions } from '@/features/tags/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createTagMock = vi.fn()
const updateTagMock = vi.fn()

vi.mock('@/features/tags/api', () => ({
  createTag: (...args: unknown[]) => createTagMock(...args),
  updateTag: (...args: unknown[]) => updateTagMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata (covered by `tag-form-metadata.test.tsx`):
 * every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/tags/use-tag-form-meta', () => ({
  useTagFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useTagForm` reads `/meta/tags` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `tag-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function tag(overrides: Partial<TagDetailWithPermissions> = {}): TagDetailWithPermissions {
  return {
    id: 9,
    name: 'VIP',
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createTagMock.mockReset()
  updateTagMock.mockReset()
})

describe('TagForm — create/edit', () => {
  it('renders the name field in create mode', () => {
    render(<TagForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createTagMock.mockResolvedValue(tag())
    const onSuccess = vi.fn()

    render(<TagForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'VIP' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createTagMock).toHaveBeenCalledTimes(1))
    expect(createTagMock).toHaveBeenCalledWith({ name: 'VIP' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(tag()))
  })

  it('hydrates the name in edit mode', () => {
    render(
      <TagForm mode={{ type: 'edit', tag: tag() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('VIP')
  })

  it('submits only the changed name on a partial update', async () => {
    updateTagMock.mockResolvedValue(tag({ name: 'Priority' }))

    render(
      <TagForm mode={{ type: 'edit', tag: tag() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Priority' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateTagMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateTagMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Priority' })
  })
})
