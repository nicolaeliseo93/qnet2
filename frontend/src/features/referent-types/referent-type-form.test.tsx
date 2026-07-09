import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createReferentTypeMock = vi.fn()
const updateReferentTypeMock = vi.fn()

vi.mock('@/features/referent-types/api', () => ({
  createReferentType: (...args: unknown[]) => createReferentTypeMock(...args),
  updateReferentType: (...args: unknown[]) => updateReferentTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata (covered by
 * `referent-type-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/referent-types/use-referent-type-form-meta', () => ({
  useReferentTypeFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useReferentTypeForm` reads `/meta/referent-types` (spec 0021) to build the
// dynamic custom-fields schema; this suite has no custom fields to exercise
// (covered by `referent-type-form-custom-fields.test.tsx`), so it resolves to
// an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function referentType(
  overrides: Partial<ReferentTypeDetailWithPermissions> = {},
): ReferentTypeDetailWithPermissions {
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
  createReferentTypeMock.mockReset()
  updateReferentTypeMock.mockReset()
})

describe('ReferentTypeForm — create/edit (AC-024)', () => {
  it('renders the name field in create mode', () => {
    render(
      <ReferentTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createReferentTypeMock.mockResolvedValue(referentType())
    const onSuccess = vi.fn()

    render(
      <ReferentTypeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createReferentTypeMock).toHaveBeenCalledTimes(1))
    expect(createReferentTypeMock).toHaveBeenCalledWith({ name: 'Sponsor' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(referentType()))
  })

  it('hydrates the name in edit mode', () => {
    render(
      <ReferentTypeForm
        mode={{ type: 'edit', referentType: referentType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Sponsor')
  })

  it('submits only the changed name on a partial update', async () => {
    updateReferentTypeMock.mockResolvedValue(referentType({ name: 'Partner' }))

    render(
      <ReferentTypeForm
        mode={{ type: 'edit', referentType: referentType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Partner' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateReferentTypeMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateReferentTypeMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Partner' })
  })
})
