import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { campaigns as campaignsEn } from '@/i18n/locales/en-campaigns'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * AC-046: the `code` field is always read-only — empty with a placeholder on
 * create, showing the saved value (still disabled) on edit. The AC-042/
 * AC-043/BR-3 Project-link behaviour lives in `campaign-project-link.test.tsx`
 * (split for file-size, engineering.md §6).
 */

const createCampaignMock = vi.fn()
const updateCampaignMock = vi.fn()

vi.mock('@/features/campaigns/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/campaigns/api')>(
    '@/features/campaigns/api',
  )
  return {
    ...actual,
    createCampaign: (...args: unknown[]) => createCampaignMock(...args),
    updateCampaign: (...args: unknown[]) => updateCampaignMock(...args),
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

/** Stubs every single-select field, keyed by its accessible trigger label (mirrors `project-form-body.test.tsx`). */
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
    project_status_id: 1,
    project_status: { id: 1, name: 'Active', color: 'blue' },
    business_function_id: 2,
    business_function: { id: 2, name: 'Sales' },
    state_id: 3,
    state: { id: 3, name: 'Lombardy' },
    product_category_id: 4,
    product_category: { id: 4, name: 'Hardware' },
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `campaigns` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
  i18n.addResourceBundle('en', 'translation', { campaigns: campaignsEn }, true, true)
})

beforeEach(() => {
  createCampaignMock.mockReset()
  updateCampaignMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
})

describe('CampaignForm — code is read-only (AC-046)', () => {
  it('shows an empty, disabled code field with the "assigned on save" placeholder on create', async () => {
    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
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
      <CampaignForm mode={{ type: 'edit', campaign: campaign() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code.value).toBe('CMP-0009')
  })
})
