import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { FieldPermission, ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0025 PARTE A: `code` is a manual, optional field — an enabled input
 * with a fallback-declaring placeholder in create (AC-010), disabled/
 * read-only showing the saved value in edit (AC-011), with a 422 duplicate
 * mapped onto the field itself (AC-012). Gated by the `code` field
 * permission, exactly like every other `MetaField`-driven control.
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

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `registry-form-metadata.test.tsx`). Renders as a button calling
 * `onChange` so create-mode tests can satisfy the required Status select
 * without a real dropdown.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" data-testid={`select-${labels.triggerLabel}`} onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  ),
}))

/**
 * The geo cascade is covered end-to-end by `geo-select.test.tsx`; here it is
 * stubbed to a single button that fills `country_id` (spec 0027 BR-4: the
 * only field this suite's submit flows need to satisfy).
 */
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
  }: {
    value: { country_id: number | null }
    onChange: (value: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }) => void
  }) => (
    <button
      type="button"
      data-testid="geo-select"
      onClick={() =>
        onChange({ country_id: 1, state_id: null, province_id: null, city_id: null })
      }
    >
      {value.country_id ?? ''}
    </button>
  ),
}))

/** `code` field-permission fixtures mirroring the spec 0025 contract's create/update ceilings. */
const CODE_EDITABLE_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const CODE_READONLY_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

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
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    source_id: null,
    source: null,
    business_function_id: null,
    business_function: null,
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
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

describe('ProjectForm — manual code (spec 0025 AC-010/AC-011)', () => {
  it('shows an enabled, empty code field with the fallback-declaring placeholder on create (AC-010)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).not.toBeDisabled()
    expect(code.value).toBe('')
    expect(code.placeholder).toBe('Leave empty to generate it automatically')
  })

  it('sends the trimmed manual code on create submit when the user fills it (AC-010)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProjectMock.mockResolvedValue(project({ code: 'ACME-2026' }))

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Code'), { target: { value: '  ACME-2026  ' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    const payload = createProjectMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.code).toBe('ACME-2026')
  })

  it('shows the saved code value, disabled/read-only, on edit (AC-011)', () => {
    render(
      <ProjectForm
        mode={{ type: 'edit', project: project({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code).toHaveAttribute('readonly')
    expect(code.value).toBe('PRJ-0007')
  })

  it('never sends a code field on edit submit (AC-011)', async () => {
    updateProjectMock.mockResolvedValue(project({ name: 'Renamed rollout' }))

    render(
      <ProjectForm
        mode={{ type: 'edit', project: project({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
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

describe('ProjectForm — 422 duplicate code (spec 0025 AC-012)', () => {
  it('maps a 422 on the code field onto the field itself, not only a toast', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProjectMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed.', errors: { code: ['The code has already been taken.'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'ACME-2026' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The code has already been taken.')).toBeInTheDocument(),
    )
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})

describe('ProjectForm — geo hierarchy (spec 0027 BR-4/AC-010)', () => {
  it('blocks the submit with the required message when no country is chosen', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Country is required.')).toBeInTheDocument())
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('submits once a country is chosen', async () => {
    createProjectMock.mockResolvedValue(project())

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    const payload = createProjectMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.country_id).toBe(1)
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
