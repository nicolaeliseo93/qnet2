import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Directive 2026-07-21 (supersedes spec 0047 D1's read-only Regione): the
 * Regione is now a first-class, always-editable picker, auto-filled from the
 * chosen Sede's `meta.state_id` but freely overridable, and always sent.
 * Split out of `lead-form-body.test.tsx` for size (engineering.md §6).
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/leads/api')>('@/features/leads/api')
  return {
    ...actual,
    createLead: (...args: unknown[]) => createLeadMock(...args),
    updateLead: (...args: unknown[]) => updateLeadMock(...args),
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

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Sede's `meta` payload the mocked select forwards through `onItemChange`
 * when picked, so the Regione auto-fill is exercisable without a real
 * dropdown/network — mirrors the shape `OperationalSiteForSelectResource` sends.
 */
const SITE_META: Record<string, unknown> = { state_id: 7, state_label: 'Lombardy' }

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `lead-form-body.test.tsx`). Renders as a button calling
 * `onChange(3)` and `onItemChange` with a canned item (`meta` only for the
 * Site field) so the auto-fill/override flows are drivable without a real
 * dropdown.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number) => void
    onItemChange?: (item: { id: number; label: string; meta?: Record<string, unknown> } | null) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-disabled={disabled ? 'true' : 'false'}
      disabled={disabled}
      onClick={() => {
        onChange(3)
        onItemChange?.({
          id: 3,
          label: `${labels.triggerLabel} 3`,
          meta: labels.triggerLabel === 'Site' ? SITE_META : undefined,
        })
      }}
    >
      {value ?? ''}
    </button>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: 'Original note.',
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('LeadFormBody — Regione (editable + Sede auto-fill, directive 2026-07-21)', () => {
  it('create mode: renders empty and enabled, no Sede chosen yet', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Region')).toBeInTheDocument())
    expect(screen.getByTestId('select-Region')).not.toBeDisabled()
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')
  })

  it('edit mode: pre-fills from the lead and stays editable', async () => {
    render(
      <LeadForm
        mode={{ type: 'edit', lead: lead({ state_id: 3, state: { id: 3, name: 'Lombardy' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Region')).toHaveTextContent('3'))
    expect(screen.getByTestId('select-Region')).not.toBeDisabled()
  })

  it('auto-fills the Regione from the picked Sede’s meta.state_id', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')

    fireEvent.click(screen.getByTestId('select-Site'))

    expect(screen.getByTestId('select-Region')).toHaveTextContent('7')
  })

  it('stays user-editable/clearable after the auto-fill (manual override)', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Site'))
    expect(screen.getByTestId('select-Region')).toHaveTextContent('7')

    // The user picks a different region by hand; the auto-fill never sticks.
    fireEvent.click(screen.getByTestId('select-Region'))
    expect(screen.getByTestId('select-Region')).toHaveTextContent('3')
  })

  it('sends the auto-filled state_id in the create payload', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))
    fireEvent.click(screen.getByTestId('select-Site'))
    fireEvent.click(screen.getByRole('switch', { name: 'Automatically convert to Opportunity' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0].state_id).toBe(7)
  })

  it('a Sede with no region leaves the current Regione untouched', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Source')).toBeInTheDocument())
    // A field whose mocked `onItemChange` carries no `meta` (any label other
    // than "Site" in this file's mock) must not touch the Regione.
    fireEvent.click(screen.getByTestId('select-Source'))
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')
  })
})
