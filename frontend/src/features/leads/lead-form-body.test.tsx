import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * AC-061 (the 6 fields render, 5 relational as `AsyncPaginatedSelect`, notes
 * as a textarea), AC-062 (field permissions: hidden vs disabled) and AC-064
 * (server 422 mapped inline, with the full a11y triad — `aria-invalid`,
 * `aria-describedby` and `role="alert"` — wired via the shared `FormControl`/
 * `FormMessage`).
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

/** Grants `opportunities.create` by default so the convert checkbox (AC-040) renders in every test unless overridden. */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Sede's `meta` payload the mocked select forwards through `onItemChange`
 * when picked, so the Regione auto-fill (directive 2026-07-21) is
 * exercisable without a real dropdown/network — mirrors the shape
 * `OperationalSiteForSelectResource` sends.
 */
const SITE_META: Record<string, unknown> = { state_id: 7, state_label: 'Lombardy' }

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `campaign-form-body.test.tsx`). Renders as a button calling
 * `onChange(3)` (and `onItemChange` with a canned item, `meta` only for the
 * Site field) so the AC-043 submit test and the Regione auto-fill test can
 * drive it without a real dropdown.
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

describe('LeadForm — fields render (AC-061, AC-016)', () => {
  it('renders the 6 relational selects (Region included, directive 2026-07-21) and a notes textarea', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.getByTestId('select-Campaign')).toBeInTheDocument()
    expect(screen.getByTestId('select-Site')).toBeInTheDocument()
    expect(screen.getByTestId('select-Region')).toBeInTheDocument()
    expect(screen.getByTestId('select-Source')).toBeInTheDocument()
    expect(screen.getByTestId('select-Operator')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })
})

describe('LeadForm — field permissions (AC-062)', () => {
  it('does not render a field marked hidden (visible: false)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          operator_id: {
            visible: false,
            hidden: true,
            editable: false,
            readonly: false,
            required: false,
            disabled: false,
          },
        },
      },
    })

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByTestId('select-Operator')).not.toBeInTheDocument()
  })

  it('renders a non-editable field disabled rather than hiding it', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          operator_id: {
            visible: true,
            hidden: false,
            editable: false,
            readonly: true,
            required: false,
            disabled: false,
          },
        },
      },
    })

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    const operator = await screen.findByTestId('select-Operator')
    expect(operator).toHaveAttribute('data-disabled', 'true')
  })
})

describe('LeadForm — server 422 mapping (AC-064)', () => {
  it('maps a 422 error onto the notes field and wires the full a11y triad', async () => {
    // Edit mode so the required Registry/Campaign already carry valid
    // defaultValues (client-side Zod validation passes trivially) and the
    // submit reaches the mocked server call under test.
    updateLeadMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { notes: ['Notes are too long.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <LeadForm mode={{ type: 'edit', lead: lead() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument())

    // Before any error: the field is clean, so no alert-role element must
    // exist yet — a permanent role on static/neutral text would be constant
    // screen-reader noise, not just a missing-positive-case gap.
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveAttribute('aria-invalid', 'false')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes are too long.')).toBeInTheDocument())
    expect(updateLeadMock).toHaveBeenCalledTimes(1)

    const notes = screen.getByRole('textbox', { name: 'Notes' })
    expect(notes).toHaveAttribute('aria-invalid', 'true')
    expect(notes.getAttribute('aria-describedby')).toContain('form-item-message')

    const message = screen.getByRole('alert')
    expect(message).toHaveTextContent('Notes are too long.')
    expect(notes.getAttribute('aria-describedby')).toContain(message.id)

    vi.restoreAllMocks()
  })
})

describe('LeadForm — extra fields editor (AC-014)', () => {
  it('shows the empty state with no rows', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.getByText('No extra fields.')).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: 'Key' })).not.toBeInTheDocument()
  })

  it('adds a row on "Add field", accepts input, and removes it on "Remove field"', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    expect(screen.getAllByRole('textbox', { name: 'Key' })).toHaveLength(1)
    expect(screen.getAllByRole('textbox', { name: 'Value' })).toHaveLength(1)
    expect(screen.queryByText('No extra fields.')).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('textbox', { name: 'Key' }), {
      target: { value: 'Original column' },
    })
    fireEvent.change(screen.getByRole('textbox', { name: 'Value' }), { target: { value: 'foo' } })
    expect(screen.getByRole('textbox', { name: 'Key' })).toHaveValue('Original column')
    expect(screen.getByRole('textbox', { name: 'Value' })).toHaveValue('foo')

    fireEvent.click(screen.getByRole('button', { name: 'Remove field' }))
    expect(screen.queryAllByRole('textbox', { name: 'Key' })).toHaveLength(0)
    expect(screen.getByText('No extra fields.')).toBeInTheDocument()
  })

  it('adds multiple rows independently', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    expect(screen.getAllByRole('textbox', { name: 'Key' })).toHaveLength(2)

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove field' })[0])
    expect(screen.getAllByRole('textbox', { name: 'Key' })).toHaveLength(1)
  })

  it('pre-populates rows from the lead extra_fields in edit mode', async () => {
    render(
      <LeadForm
        mode={{ type: 'edit', lead: lead({ extra_fields: { 'Original column': 'foo' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Key' })).toBeInTheDocument())
    expect(screen.getByRole('textbox', { name: 'Key' })).toHaveValue('Original column')
    expect(screen.getByRole('textbox', { name: 'Value' })).toHaveValue('foo')
  })
})

describe('LeadForm — convert to opportunity (spec 0044, AC-040..AC-043)', () => {
  it('AC-040: shows the control in create mode when the actor has opportunities.create', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    // Defaults ON for an actor who can create Opportunities.
    expect(
      screen.getByRole('switch', { name: 'Automatically convert to Opportunity' }),
    ).toBeChecked()
  })

  it('AC-040: hides the control without opportunities.create', async () => {
    canMock.mockReturnValue(false)

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(
      screen.queryByRole('switch', { name: 'Automatically convert to Opportunity' }),
    ).not.toBeInTheDocument()
  })

  it('AC-040: hides the control in edit mode regardless of permission', async () => {
    render(
      <LeadForm mode={{ type: 'edit', lead: lead() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(
      screen.queryByRole('switch', { name: 'Automatically convert to Opportunity' }),
    ).not.toBeInTheDocument()
  })

  it('directive 2026-07-21 (supersedes AC-041): submitting with the control on and Operator/Site empty still submits, with a null supervisor-deriving pair', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))

    // The control is ON by default; submit straight away with Operator/Site empty.
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    const payload = createLeadMock.mock.calls[0][0]
    expect(payload.convert_to_opportunity).toBe(true)
    expect(payload.operator_id).toBeNull()
    expect(payload.operational_site_id).toBeNull()
  })

  it('AC-042: Operator and Site stay optional and the form submits with the control off', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))
    // Turn the default-ON control OFF so Operator/Site stay optional.
    fireEvent.click(screen.getByRole('switch', { name: 'Automatically convert to Opportunity' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    const payload = createLeadMock.mock.calls[0][0]
    expect(payload.operator_id).toBeNull()
    expect(payload.operational_site_id).toBeNull()
    expect(payload.convert_to_opportunity).toBe(false)
  })

  it('AC-043: the create payload carries convert_to_opportunity: true once Operator/Site are set', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())

    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))
    // The control is ON by default; just fill the Operator/Site it requires.
    fireEvent.click(screen.getByTestId('select-Operator'))
    fireEvent.click(screen.getByTestId('select-Site'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    const payload = createLeadMock.mock.calls[0][0]
    expect(payload.convert_to_opportunity).toBe(true)
    expect(payload.operator_id).toBe(3)
    expect(payload.operational_site_id).toBe(3)
  })
})
