import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * AC-071 (every field of the contract renders), AC-072 (referent disabled
 * without a registry, scoped by `registry_id` once chosen and reset on registry
 * change; commercial/reporter are free and, per user directive 2026-07-17, are
 * never auto-filled from the anagrafica), AC-074 (field permissions: hidden vs
 * disabled), AC-107 (amendment rev.3: the name auto-fill override).
 */

const createOpportunityMock = vi.fn()
const updateOpportunityMock = vi.fn()

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
    updateOpportunity: (...args: unknown[]) => updateOpportunityMock(...args),
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

const TEST_REGISTRY_WITH_DEFAULTS = 10
const TEST_REGISTRY_WITHOUT_DEFAULTS = 20
const TEST_BUSINESS_FUNCTION = 40
const TEST_PRODUCT_CATEGORY = 500

/** Fixed selection ids exposed per field (by accessible trigger label), so BR-4 side effects are exercisable without a real dropdown. */
const SELECT_IDS: Record<string, number[]> = {
  Registry: [TEST_REGISTRY_WITH_DEFAULTS, TEST_REGISTRY_WITHOUT_DEFAULTS],
  'Business function 1': [TEST_BUSINESS_FUNCTION],
  'Product category 1': [TEST_PRODUCT_CATEGORY],
}

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `campaign-project-link.test.tsx`): exposes the current value,
 * disabled state and the `params` this instance received (BR-4 scoping), plus
 * a "select" affordance per fixed id so onChange side effects are exercisable.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    params,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    params?: Record<string, string | number>
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <span data-testid={`params-${labels.triggerLabel}`}>{JSON.stringify(params ?? null)}</span>
      {(SELECT_IDS[labels.triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
      <button type="button" onClick={() => onChange(null)}>{`clear ${labels.triggerLabel}`}</button>
    </div>
  ),
}))

/**
 * Controls the one-shot `meta` fetch behind the registry prefill (BR-4/A-5)
 * and the product-lines draft picker's label resolution (amendment rev.3):
 * both go through the generic `fetchForSelect`, mocked here per resource+id.
 */
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

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOpportunityMock.mockReset()
  updateOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITH_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITH_DEFAULTS,
            label: 'Acme S.p.A.',
            meta: {
              commercial: { id: 71, name: 'Sara Conti' },
              reporter: { id: 81, name: 'Elio Fabbri' },
              // A-5: account managers inherited into manager_slots (gap-aware by position).
              managers: [
                { id: 91, name: 'Gina Manager', position: 1 },
                { id: 93, name: 'Turi Manager', position: 3 },
              ],
            },
          },
        ],
      }
    }
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITHOUT_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITHOUT_DEFAULTS,
            label: 'Beta Srl',
            meta: { commercial: null, reporter: null, managers: [] },
          },
        ],
      }
    }
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION, label: 'Sales' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY, label: 'Consulting' }] }
    }
    return EMPTY_PAGE
  })
})

describe('OpportunityFormBody — fields render (AC-071)', () => {
  it('renders the name input and every relational select', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.getByRole('textbox', { name: 'Name' })).toBeInTheDocument()
    expect(screen.getByTestId('select-Contact')).toBeInTheDocument()
    expect(screen.getByTestId('select-Sales rep')).toBeInTheDocument()
    expect(screen.getByTestId('select-Reporter')).toBeInTheDocument()
    expect(screen.getByTestId('select-Source')).toBeInTheDocument()
    expect(screen.getByTestId('select-Supervisor')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add account manager' })).toBeInTheDocument()
    // Amendment rev.3: no product-line row renders until "Add" is clicked (mirrors manager slots).
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument()
    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
  })
})

describe('OpportunityFormBody — referent scoping + free commercial/reporter (AC-093)', () => {
  it('disables only the referent until a registry is chosen; commercial/reporter stay enabled', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    // A-3: only referent is anagrafica-scoped; commercial/reporter are free.
    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('true'))
    expect(screen.getByTestId('disabled-Sales rep')).toHaveTextContent('false')
    expect(screen.getByTestId('disabled-Reporter')).toHaveTextContent('false')
  })

  it('scopes ONLY the referent by registry_id; commercial/reporter receive no params and are NOT auto-filled (user directive 2026-07-17)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false'))
    expect(screen.getByTestId('params-Contact')).toHaveTextContent(
      JSON.stringify({ registry_id: TEST_REGISTRY_WITH_DEFAULTS }),
    )
    // A-3: commercial/reporter are the whole platform list — no registry_id param.
    expect(screen.getByTestId('params-Sales rep')).toHaveTextContent('null')
    expect(screen.getByTestId('params-Reporter')).toHaveTextContent('null')
    // They are independent of the anagrafica: picking a registry must NOT
    // auto-fill them from its defaults — they stay empty.
    expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('')
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('')
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
  })

  it('resets referent on registry change but leaves the manually chosen commercial/reporter untouched (A-3 independence)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    // The user manually picks a referent (scoped), a commercial and a reporter.
    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false'))
    screen.getByRole('button', { name: 'select Contact 1' }).click()
    screen.getByRole('button', { name: 'select Sales rep 1' }).click()
    screen.getByRole('button', { name: 'select Reporter 1' }).click()
    await waitFor(() => expect(screen.getByTestId('value-Contact')).toHaveTextContent('1'))

    // Changing the anagrafica resets ONLY the scoped referent; the independent
    // commercial/reporter keep the user's choices.
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITHOUT_DEFAULTS}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Contact')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('1')
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('1')
  })

  it('inherits the account managers of the chosen anagrafica into gap-aware slots, then clears them for one without (AC-095)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    // Positions 1 and 3 -> slots 1 and 3 filled, slot 2 an empty gap.
    await waitFor(() => expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('91'))
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('93')

    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITHOUT_DEFAULTS}` }).click()

    // No managers on the new anagrafica -> slots cleared.
    await waitFor(() =>
      expect(screen.queryByTestId('value-Account manager 1')).not.toBeInTheDocument(),
    )
  })
})

describe('OpportunityFormBody — product lines + name auto-fill (AC-106/107)', () => {
  it('scopes the row category by the row own business function', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')

    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('params-Product category 1')).toHaveTextContent(
      JSON.stringify({ business_function_id: TEST_BUSINESS_FUNCTION }),
    )
  })

  it('auto-fills the name from the added row, then keeps the manual override after a hand-edit', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY}` }).click()

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('Consulting'))

    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'My own name' } })

    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('My own name')
  })

  it('blocks the submit and shows an error when a row is left incomplete', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // The row's category is left unset on purpose.

    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Enterprise deal' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Each row requires both a business function and a product category.'),
      ).toBeInTheDocument(),
    )
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})

describe('OpportunityFormBody — field permissions (AC-074)', () => {
  it('does not render a field marked hidden (visible: false)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          supervisor_id: {
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

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByTestId('select-Supervisor')).not.toBeInTheDocument()
  })

  it('renders a non-editable field disabled rather than hiding it', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          source_id: {
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

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    const source = await screen.findByTestId('disabled-Source')
    expect(source).toHaveTextContent('true')
  })
})

/** AC-075: create-from-lead mode (spec 0040 MT-6) — locked fields precompiled + forceDisabled, banner shown, free fields stay editable. */
describe('OpportunityFormBody — create from lead (BR-1/BR-2, AC-075)', () => {
  it('shows the origin banner, locks the derived fields precompiled, and seeds the editable product-line row', async () => {
    render(
      <OpportunityForm
        mode={{
          type: 'create',
          fromLead: {
            leadId: 9,
            values: {
              // spec 0041 D-3/AC-050: no longer a derived field.
              referent_id: null,
              source_id: 20,
              registry_id: 30,
            },
            references: {
              source: { id: 20, name: 'Web' },
              registry: { id: 30, name: 'Acme S.p.A.' },
            },
            lockedFields: ['registry_id', 'source_id'],
            productLines: [
              {
                id: 900,
                business_function: { id: 40, name: 'Sales' },
                product_category: { id: 50, name: 'Consulting' },
              },
            ],
          },
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    // AC-051: the origin banner sources its name from the registry, not the referent.
    expect(screen.getByRole('status')).toHaveTextContent('Acme S.p.A.')

    expect(screen.getByTestId('value-Registry')).toHaveTextContent('30')
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true')
    // AC-051: referent_id is no longer derived/locked by the lead — free and
    // editable, gated only by the registry now being chosen.
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Source')).toHaveTextContent('20')
    expect(screen.getByTestId('disabled-Source')).toHaveTextContent('true')

    // Amendment rev.3 (AC-102/103): the seeded row is editable/removable —
    // never disabled — and its category already auto-fills the name.
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('40')
    expect(screen.getByTestId('disabled-Business function 1')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false')
    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('Consulting')
    expect(screen.getByRole('button', { name: 'Remove product line' })).toBeInTheDocument()
  })

  it('renders no banner and no locked field for a plain manual create', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false')
  })
})
