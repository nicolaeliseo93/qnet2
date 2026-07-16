import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { campaigns as campaignsEn } from '@/i18n/locales/en-campaigns'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ProjectForSelectMeta } from '@/features/projects/for-select-api'
import type { ProjectGeoMeta } from '@/features/campaigns/campaign-geo'

/**
 * Spec 0023 FRONTEND acceptance criteria:
 * - AC-042: picking a Project prefills Client/Source/Partner (editable) and
 *   forces the 3 classification fields read-only from the project's values,
 *   which are excluded from the payload regardless of what they display.
 * - AC-043: clearing the Project makes the 3 classification fields editable
 *   and required again (client validation error when left empty).
 * - BR-3: the backend's 422 budget message is shown verbatim, not a generic one.
 * Spec 0027 BR-5 (replaces BR-2 for geo, D-3 — REWRITTEN, not tampered: the
 * requirement changed): picking a project also prefills+locks the geo levels
 * the project fills (`<GeoSelect lockedLevels>`), excluded from the payload;
 * clearing the project unlocks and resets all four, re-requiring `country_id`.
 * AC-046 (the `code` field) lives in `campaign-form-body.test.tsx`.
 */

const TEST_PROJECT_ID = 42

const createCampaignMock = vi.fn()
const updateCampaignMock = vi.fn()
const fetchCampaignNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/campaigns/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/campaigns/api')>(
    '@/features/campaigns/api',
  )
  return {
    ...actual,
    createCampaign: (...args: unknown[]) => createCampaignMock(...args),
    updateCampaign: (...args: unknown[]) => updateCampaignMock(...args),
    fetchCampaignNextCode: () => fetchCampaignNextCodeMock(),
  }
})

const fetchProjectsForSelectMock = vi.fn()
vi.mock('@/features/projects/for-select-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/projects/for-select-api')>(
    '@/features/projects/for-select-api',
  )
  return {
    ...actual,
    fetchProjectsForSelect: (...args: unknown[]) => fetchProjectsForSelectMock(...args),
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
 * (mirrors `project-form-body.test.tsx`), but ALSO exposes a "select"/"clear"
 * affordance per field so the Project picker's onChange (AC-042/AC-043) is
 * exercisable end to end.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <button type="button" onClick={() => onChange(TEST_PROJECT_ID)}>
        {`select ${labels.triggerLabel}`}
      </button>
      <button type="button" onClick={() => onChange(null)}>
        {`clear ${labels.triggerLabel}`}
      </button>
    </div>
  ),
}))

/**
 * `GeoSelect` is covered by its own test; here a controllable read-only stub
 * exposes the wired value and `lockedLevels` so BR-5's prefill+lock (AC-042)
 * and unlock-on-unlink (AC-043) are observable without a real cascade.
 */
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    lockedLevels,
  }: {
    value: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }
    lockedLevels?: readonly string[]
  }) => (
    <div
      data-testid="geo-select"
      data-country={value.country_id ?? ''}
      data-state={value.state_id ?? ''}
      data-province={value.province_id ?? ''}
      data-city={value.city_id ?? ''}
      data-locked={(lockedLevels ?? []).join(',')}
    />
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function projectForSelectItem(overrides: {
  meta?: Partial<ProjectForSelectMeta>
  geo?: Partial<ProjectGeoMeta>
} = {}) {
  return {
    id: TEST_PROJECT_ID,
    label: 'PRJ-0042 — Acme rollout',
    meta: {
      registry: { id: 11, label: 'Acme Srl' },
      source: { id: 21, label: 'Referral' },
      partner: { id: 31, label: 'Jane Partner' },
      pipeline_status: { id: 41, label: 'Active' },
      business_function: { id: 51, label: 'Marketing' },
      state: null,
      product_category: { id: 71, label: 'Hardware' },
      total_budget: '1000.00',
      allocated_budget: '600.00',
      remaining_budget: '400.00',
      ...overrides.meta,
      geo: {
        country: { id: 61, name: 'Italy' },
        state: null,
        province: null,
        city: null,
        ...overrides.geo,
      },
    },
  }
}

function campaign(
  overrides: Partial<CampaignDetailWithPermissions> = {},
): CampaignDetailWithPermissions {
  return {
    id: 9,
    code: 'CMP-0009',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    registry_id: null,
    registry: null,
    source_id: null,
    source: null,
    partner_id: null,
    partner: null,
    derived_from_project: false,
    pipeline_status_id: 1,
    pipeline_status: { id: 1, name: 'Active', color: 'blue' },
    business_function_id: 2,
    business_function: { id: 2, name: 'Sales' },
    country_id: 61,
    country: { id: 61, name: 'Italy' },
    state_id: 3,
    state: { id: 3, name: 'Lombardy' },
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'state',
    geo_locked_levels: [],
    product_category_id: 4,
    product_category: { id: 4, name: 'Hardware' },
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { campaigns: campaignsEn }, true, true)
})

beforeEach(() => {
  createCampaignMock.mockReset()
  updateCampaignMock.mockReset()
  fetchCampaignNextCodeMock.mockReset()
  fetchCampaignNextCodeMock.mockResolvedValue('CMP-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchProjectsForSelectMock.mockReset()
  fetchProjectsForSelectMock.mockResolvedValue({
    items: [projectForSelectItem()],
    export_link: null,
    pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  })
})

describe('CampaignForm — selecting a Project (AC-042)', () => {
  it('prefills Client/Source/Partner, forces the 3 classification fields read-only, and locks the geo levels the project fills (BR-5)', async () => {
    createCampaignMock.mockResolvedValue(campaign({ project_id: TEST_PROJECT_ID }))

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Linked campaign' } })
    fireEvent.click(screen.getByRole('button', { name: 'select Project' }))

    await waitFor(() => expect(screen.getByTestId('value-Client')).toHaveTextContent('11'))
    expect(screen.getByTestId('value-Source')).toHaveTextContent('21')
    expect(screen.getByTestId('value-Partner')).toHaveTextContent('31')
    expect(screen.getByTestId('value-Status')).toHaveTextContent('41')
    expect(screen.getByTestId('value-Business function')).toHaveTextContent('51')
    expect(screen.getByTestId('value-Product category')).toHaveTextContent('71')

    // The 3 derived fields are forced read-only while linked; Client/Source/Partner stay editable.
    expect(screen.getByTestId('disabled-Status')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Business function')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Product category')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Client')).toHaveTextContent('false')
    expect(screen.getByTestId('disabled-Source')).toHaveTextContent('false')
    expect(screen.getByTestId('disabled-Partner')).toHaveTextContent('false')

    // BR-5: the project's own country is prefilled and locked; the rest stay editable/empty.
    await waitFor(() => expect(screen.getByTestId('geo-select')).toHaveAttribute('data-country', '61'))
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', 'country')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-state', '')

    // Dates are required even for a linked campaign (not inherited): fill them
    // in the collapsed Planning & budget section before submitting.
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('business_function_id')
    expect(payload).not.toHaveProperty('product_category_id')
    expect(payload).not.toHaveProperty('country_id')
    expect(payload.project_id).toBe(TEST_PROJECT_ID)
    expect(payload.registry_id).toBe(11)
    expect(payload.source_id).toBe(21)
    expect(payload.partner_id).toBe(31)
  })
})

describe('CampaignForm — deselecting the Project (AC-043)', () => {
  it('clears and unlocks the 3 classification fields and the 4 geo levels, requiring Country again (spec 0039 D-3: Status is no longer required)', async () => {
    const linkedCampaign = campaign({
      project_id: TEST_PROJECT_ID,
      project: { id: TEST_PROJECT_ID, code: 'PRJ-0042', name: 'Acme rollout' },
      derived_from_project: true,
      geo_locked_levels: ['country'],
    })

    render(
      <CampaignForm mode={{ type: 'edit', campaign: linkedCampaign }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('disabled-Status')).toHaveTextContent('true')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', 'country')

    fireEvent.click(screen.getByRole('button', { name: 'clear Project' }))

    await waitFor(() => expect(screen.getByTestId('disabled-Status')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Status')).toHaveTextContent('')
    expect(screen.getByTestId('value-Business function')).toHaveTextContent('')
    expect(screen.getByTestId('value-Product category')).toHaveTextContent('')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', '')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-country', '')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Country is required when the linked project does not provide one.'),
      ).toBeInTheDocument(),
    )
    expect(updateCampaignMock).not.toHaveBeenCalled()
  })
})

describe('CampaignForm — BR-3 budget 422', () => {
  it('shows the backend insufficient-budget message verbatim, not a generic error', async () => {
    const backendMessage =
      'Budget insufficiente sul progetto PRJ-0042: budget 1000.00, già allocato 600.00, residuo 400.00, richiesto 1000.00.'

    updateCampaignMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed.', errors: { total_budget: [backendMessage] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <CampaignForm mode={{ type: 'edit', campaign: campaign() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // "Planning & budget" is now a collapsible, default-closed section
    // (UX-only refactor): open it before reaching the "Total budget" field.
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
    fireEvent.change(screen.getByLabelText('Total budget'), { target: { value: '1000' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText(backendMessage)).toBeInTheDocument())
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})
