import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * AC-046: the `code` field is always read-only — empty with a placeholder on
 * create, showing the saved value (still disabled) on edit — and is never
 * part of the RHF-controlled values, so it can never leak into the payload.
 */

const createProjectMock = vi.fn()
const updateProjectMock = vi.fn()

vi.mock('@/features/projects/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/projects/api')>(
    '@/features/projects/api',
  )
  return {
    ...actual,
    createProject: (...args: unknown[]) => createProjectMock(...args),
    updateProject: (...args: unknown[]) => updateProjectMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

/** Stubs every single-select field, keyed by its accessible trigger label (mirrors `registry-form-metadata.test.tsx`). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    labels,
  }: {
    value: number | null
    labels: { triggerLabel: string }
  }) => <div data-testid={`select-${labels.triggerLabel}`}>{value ?? ''}</div>,
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function project(
  overrides: Partial<ProjectDetailWithPermissions> = {},
): ProjectDetailWithPermissions {
  return {
    id: 7,
    code: 'PRJ-0007',
    name: 'Acme rollout',
    description: null,
    registry_id: null,
    registry: null,
    project_status_id: 3,
    project_status: { id: 3, name: 'Active', color: 'blue' },
    source_id: null,
    source: null,
    business_function_id: null,
    business_function: null,
    state_id: null,
    state: null,
    product_category_id: null,
    product_category: null,
    partner_id: null,
    partner: null,
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    allocated_budget: '0.00',
    remaining_budget: null,
    campaigns_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `projects` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
  i18n.addResourceBundle('en', 'translation', { projects: projectsEn }, true, true)
})

beforeEach(() => {
  createProjectMock.mockReset()
  updateProjectMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
})

describe('ProjectForm — code is read-only (AC-046)', () => {
  it('shows an empty, disabled code field with the "assigned on save" placeholder on create', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code.value).toBe('')
    expect(code.placeholder).toBe('assigned on save')
  })

  it('shows the saved code value, disabled, on edit', () => {
    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code.value).toBe('PRJ-0007')
  })

  it('never sends a code field on edit submit', async () => {
    updateProjectMock.mockResolvedValue(project({ name: 'Renamed rollout' }))

    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Renamed rollout' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProjectMock).toHaveBeenCalledTimes(1))
    const payload = updateProjectMock.mock.calls[0][1] as Record<string, unknown>
    expect(payload).not.toHaveProperty('code')
    expect(payload).toEqual({ name: 'Renamed rollout' })
  })
})

describe('ProjectForm — end_date validation (BR-6)', () => {
  it('rejects an end_date earlier than the start_date', async () => {
    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-06-01' } })
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-01-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('End date must not be earlier than the start date.')).toBeInTheDocument(),
    )
    expect(updateProjectMock).not.toHaveBeenCalled()
  })
})
