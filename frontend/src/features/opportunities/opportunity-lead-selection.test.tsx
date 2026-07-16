import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Amendment rev.1, A-1: the in-form "Lead" select (AC-086/087) and the EDIT
 * form's read-only Lead field (AC-088). Split out of `opportunity-form-body.test.tsx`
 * for file size (engineering.md §6) — every other field's own behavior
 * (BR-4 scoping, field permissions, deep-link create-from-lead) is covered
 * there; here only the Lead field itself is under test.
 */

const createOpportunityMock = vi.fn()

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
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

const TEST_REGISTRY_ID = 10
const TEST_LEAD_ID = 900
const TEST_LEAD_ALREADY_LINKED_ID = 901

/** Fixed selection ids exposed per field (by accessible trigger label), mirrors `opportunity-form-body.test.tsx`. */
const SELECT_IDS: Record<string, number[]> = {
  Lead: [TEST_LEAD_ID, TEST_LEAD_ALREADY_LINKED_ID],
}

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
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      {(SELECT_IDS[labels.triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
      <button type="button" onClick={() => onChange(null)}>{`clear ${labels.triggerLabel}`}</button>
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
  }
})

/**
 * Controls the in-form "Lead" select's one-shot defaults fetch (spec 0040
 * A-1): `useOpportunityLeadSelection` calls this directly, sharing the exact
 * same underlying endpoint as the `?lead_id=N` deep-link.
 */
const fetchOpportunityDefaultsOnceMock = vi.fn()
vi.mock('@/features/opportunities/opportunity-defaults-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/opportunity-defaults-api')>(
    '@/features/opportunities/opportunity-defaults-api',
  )
  return {
    ...actual,
    fetchOpportunityDefaultsOnce: (...args: unknown[]) => fetchOpportunityDefaultsOnceMock(...args),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** The "Go to the opportunity" CTA (AC-087) renders a `<Link>`: needs a router context. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
}

function editOpportunity(
  overrides: Partial<OpportunityDetailWithPermissions> = {},
): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: TEST_REGISTRY_ID,
    registry: { id: TEST_REGISTRY_ID, name: 'Acme S.p.A.' },
    company_id: 1,
    company: { id: 1, name: 'Acme Group' },
    company_site_id: 1,
    company_site: { id: 1, name: 'HQ' },
    operational_site_id: 30,
    operational_site: { id: 30, label: 'Via Roma 1 - Milano' },
    business_function_id: 40,
    business_function: { id: 40, name: 'Sales' },
    referent_id: 10,
    referent: { id: 10, name: 'Mario Rossi' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: 20,
    source: { id: 20, name: 'Web' },
    product_category_id: 50,
    product_category: { id: 50, name: 'Consulting' },
    lead_id: 900,
    lead: { id: 900, label: 'Mario Rossi' },
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [
      'referent_id',
      'source_id',
      'operational_site_id',
      'registry_id',
      'business_function_id',
      'product_category_id',
    ],
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
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)

  fetchOpportunityDefaultsOnceMock.mockReset()
  fetchOpportunityDefaultsOnceMock.mockImplementation(async (_client: unknown, leadId: number) => {
    if (leadId === TEST_LEAD_ALREADY_LINKED_ID) {
      return {
        lead_id: leadId,
        existing_opportunity_id: 777,
        values: {
          referent_id: null,
          source_id: null,
          operational_site_id: null,
          registry_id: null,
          business_function_id: null,
          product_category_id: null,
        },
        references: {
          referent: { id: 1, name: 'Mario Rossi' },
          source: null,
          operational_site: null,
          registry: null,
          business_function: null,
          product_category: null,
        },
        locked_fields: [],
      }
    }
    return {
      lead_id: leadId,
      existing_opportunity_id: null,
      values: {
        referent_id: 10,
        source_id: 20,
        operational_site_id: 30,
        registry_id: TEST_REGISTRY_ID,
        business_function_id: 40,
        product_category_id: 50,
      },
      references: {
        referent: { id: 10, name: 'Mario Rossi' },
        source: { id: 20, name: 'Web' },
        operational_site: { id: 30, label: 'Via Roma 1 - Milano' },
        registry: { id: TEST_REGISTRY_ID, name: 'Acme S.p.A.' },
        business_function: { id: 40, name: 'Sales' },
        product_category: { id: 50, name: 'Consulting' },
      },
      locked_fields: [
        'referent_id',
        'source_id',
        'operational_site_id',
        'registry_id',
        'business_function_id',
        'product_category_id',
      ],
    }
  })
})

describe('OpportunityFormBody — in-form Lead select (AC-086/087)', () => {
  it('applies BR-1 values and BR-2 locks when a lead is picked, and shows the origin banner', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Registry')).toHaveTextContent(String(TEST_REGISTRY_ID)))
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('10')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Source')).toHaveTextContent('20')
    expect(screen.getByTestId('disabled-Source')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Operational site')).toHaveTextContent('30')
    expect(screen.getByTestId('disabled-Operational site')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Business function')).toHaveTextContent('40')
    expect(screen.getByTestId('disabled-Business function')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Product category')).toHaveTextContent('50')
    expect(screen.getByTestId('disabled-Product category')).toHaveTextContent('true')
    expect(screen.getByRole('status')).toHaveTextContent('Mario Rossi')
  })

  it('resets and unlocks the derived fields when the lead selection is cleared', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true'))

    screen.getByRole('button', { name: 'clear Lead' }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Registry')).toHaveTextContent('')
    // Referent is still gated by registry being unset again, not by the lead lock.
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('value-Operational site')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Operational site')).toHaveTextContent('false')
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('sends lead_id and omits the locked fields when submitting from a picked lead', async () => {
    createOpportunityMock.mockResolvedValue({ id: 1 })

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true'))

    // company_id/company_site_id are never derivable (A-2): still required, filled manually.
    screen.getByRole('button', { name: 'select Company 1' }).click()
    screen.getByRole('button', { name: 'select Company site 1' }).click()
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Enterprise deal' } })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOpportunityMock).toHaveBeenCalledTimes(1))
    const payload = createOpportunityMock.mock.calls[0][0]
    expect(payload.lead_id).toBe(TEST_LEAD_ID)
    expect(payload).not.toHaveProperty('registry_id')
    expect(payload).not.toHaveProperty('referent_id')
    expect(payload).not.toHaveProperty('source_id')
    expect(payload).not.toHaveProperty('operational_site_id')
    expect(payload).not.toHaveProperty('business_function_id')
    expect(payload).not.toHaveProperty('product_category_id')
    expect(payload.company_id).toBe(1)
    expect(payload.company_site_id).toBe(1)
  })

  it('blocks the submit and shows a message when the picked lead already has an opportunity (AC-087)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ALREADY_LINKED_ID}` }).click()

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('This lead already has an opportunity'),
    )
    expect(screen.getByRole('link', { name: 'Go to the opportunity' })).toHaveAttribute(
      'href',
      '/opportunities/777',
    )
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled()
    // Never applied: no derived value written, no lock either.
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})

describe('OpportunityFormBody — edit mode Lead field (AC-088)', () => {
  it('shows the linked lead read-only, never as an editable select', async () => {
    render(
      <OpportunityForm mode={{ type: 'edit', opportunity: editOpportunity() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const leadField = await screen.findByDisplayValue('Mario Rossi')
    expect(leadField).toBeDisabled()
    expect(screen.queryByTestId('select-Lead')).not.toBeInTheDocument()
  })

  it('renders no Lead field at all for an opportunity with no linked lead', async () => {
    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity({ lead_id: null, lead: null }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByTestId('select-Business function')
    expect(screen.queryByText('Lead')).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Lead')).not.toBeInTheDocument()
  })
})
