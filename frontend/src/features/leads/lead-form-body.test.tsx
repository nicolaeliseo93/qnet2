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

/** Stubs every single-select field, keyed by its accessible trigger label (mirrors `campaign-form-body.test.tsx`). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    labels,
    disabled,
  }: {
    value: number | null
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`} data-disabled={disabled ? 'true' : 'false'}>
      {value ?? ''}
    </div>
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
    referent_id: 10,
    referent: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: 'Original note.',
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
})

describe('LeadForm — fields render (AC-061)', () => {
  it('renders the 6 fields: 5 relational selects and a notes textarea', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Contact')).toBeInTheDocument())
    expect(screen.getByTestId('select-Campaign')).toBeInTheDocument()
    expect(screen.getByTestId('select-Site')).toBeInTheDocument()
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

    await waitFor(() => expect(screen.getByTestId('select-Contact')).toBeInTheDocument())
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
    // Edit mode so the required Contact/Campaign already carry valid
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
