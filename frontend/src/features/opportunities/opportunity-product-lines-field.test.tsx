import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunityProductLinesField } from '@/features/opportunities/opportunity-product-lines-field'
import { useOpportunityNameAutofill } from '@/features/opportunities/use-opportunity-name-autofill'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityProductLine } from '@/features/opportunities/types'

/**
 * Spec 0040 amendment rev.3, AC-106/107: the inline business-function +
 * product-category row editor, styled/interacted like `ManagerSlotsField`
 * ("Add" appends an empty row, edited in place, removed with a trash icon).
 * Covers adding an empty row, category options scoped by the row's own
 * function, removal, label resolution from already-known rows (no redundant
 * fetch) and the name auto-fill (composition + manual-override stickiness).
 * Row-completeness validation (both ids required before submit) lives in
 * `opportunity-schema.test.ts`/`opportunity-form-body.test.tsx`.
 */

const TEST_BUSINESS_FUNCTION_A = 1
const TEST_BUSINESS_FUNCTION_B = 2
const TEST_PRODUCT_CATEGORY_A = 11
const TEST_PRODUCT_CATEGORY_B = 22

const SELECT_IDS: Record<string, number[]> = {
  'Business function 1': [TEST_BUSINESS_FUNCTION_A, TEST_BUSINESS_FUNCTION_B],
  'Business function 2': [TEST_BUSINESS_FUNCTION_A, TEST_BUSINESS_FUNCTION_B],
  'Product category 1': [TEST_PRODUCT_CATEGORY_A, TEST_PRODUCT_CATEGORY_B],
  'Product category 2': [TEST_PRODUCT_CATEGORY_A, TEST_PRODUCT_CATEGORY_B],
}

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
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function baseFormValues(overrides: Partial<OpportunityFormValues> = {}): OpportunityFormValues {
  return {
    name: '',
    registry_id: 1,
    opportunity_status_id: 5,
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    source_id: null,
    state_id: null,
    opportunity_workflow_status_id: null,
    product_lines: [],
    products_of_interest: [],
    manager_slots: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    success_probability: 0,
    ...overrides,
  }
}

interface HarnessProps {
  defaultValues?: OpportunityFormValues
  knownLines?: OpportunityProductLine[]
}

/**
 * Mirrors the real wiring (`opportunity-product-lines-section.tsx`'s
 * `MetaField`): `product_lines` flows through RHF like any other field, the
 * name input disables the auto-fill on a hand-edit exactly as
 * `opportunity-form-body.tsx` does.
 */
function Harness({ defaultValues, knownLines = [] }: HarnessProps) {
  const form = useForm<OpportunityFormValues>({ defaultValues: defaultValues ?? baseFormValues() })
  const nameAutofill = useOpportunityNameAutofill(false)
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <>
      <input
        aria-label="Name"
        {...form.register('name')}
        onChange={(event) => {
          nameAutofill.disable()
          form.setValue('name', event.target.value, { shouldDirty: true })
        }}
      />
      <OpportunityProductLinesField
        value={productLines}
        onChange={(next) => form.setValue('product_lines', next, { shouldDirty: true })}
        setValue={form.setValue}
        knownLines={knownLines}
        nameAutofill={nameAutofill}
      />
    </>
  )
}

function renderHarness(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION_A)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION_A, label: 'Sales' }] }
    }
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION_B)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION_B, label: 'Marketing' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY_A)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY_A, label: 'Consulting' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY_B)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY_B, label: 'Training' }] }
    }
    return EMPTY_PAGE
  })
})

describe('OpportunityProductLinesField (AC-106)', () => {
  it('renders no row and an enabled "Add" button when empty', () => {
    renderHarness()
    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('adds an empty row on "Add", with the category disabled until a function is chosen', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument()
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
  })

  it('scopes the row category by the row own business function (AC-104)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('params-Product category 1')).toHaveTextContent(
      JSON.stringify({ business_function_id: TEST_BUSINESS_FUNCTION_A }),
    )
  })

  it('resets the category when the row function changes', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()
    await waitFor(() =>
      expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(TEST_PRODUCT_CATEGORY_A)),
    )

    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_B}` }))

    await waitFor(() => expect(screen.getByTestId('value-Product category 1')).toBeEmptyDOMElement())
    // The new function (B) is still set, so the category select stays enabled — just cleared.
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false')
  })

  it('removes a row', () => {
    renderHarness({
      knownLines: [
        {
          id: 1,
          business_function: { id: TEST_BUSINESS_FUNCTION_A, name: 'Sales' },
          product_category: { id: TEST_PRODUCT_CATEGORY_A, name: 'Consulting' },
        },
      ],
      defaultValues: baseFormValues({
        product_lines: [{ business_function_id: TEST_BUSINESS_FUNCTION_A, product_category_id: TEST_PRODUCT_CATEGORY_A }],
      }),
    })

    expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove product line' }))

    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
  })

  it('resolves row labels from knownLines without a fetch (pre-fill from lead/edit, AC-103)', () => {
    renderHarness({
      knownLines: [
        { id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 50, name: 'Consulting' } },
      ],
      defaultValues: baseFormValues({ product_lines: [{ business_function_id: 40, product_category_id: 50 }] }),
    })

    expect(fetchForSelectMock).not.toHaveBeenCalled()
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('40')
  })

  it('auto-fills the name from the row once its category is picked (AC-107)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))

    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()

    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Consulting'))
  })

  it('composes multiple category names in row order (AC-107)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()
    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Consulting'))

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 2 ${TEST_BUSINESS_FUNCTION_B}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 2 ${TEST_PRODUCT_CATEGORY_B}` }).click()

    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Consulting + Training'))
  })

  it('keeps a hand-edited name after adding another row (AC-107)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()
    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Consulting'))

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'My own name' } })
    expect(screen.getByLabelText('Name')).toHaveValue('My own name')

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 2 ${TEST_BUSINESS_FUNCTION_B}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 2 ${TEST_PRODUCT_CATEGORY_B}` }).click()

    expect(screen.getByLabelText('Name')).toHaveValue('My own name')
  })

  it('recomputes the name after removing a row while still in auto mode', async () => {
    renderHarness({
      knownLines: [
        {
          id: 1,
          business_function: { id: TEST_BUSINESS_FUNCTION_A, name: 'Sales' },
          product_category: { id: TEST_PRODUCT_CATEGORY_A, name: 'Consulting' },
        },
        {
          id: 2,
          business_function: { id: TEST_BUSINESS_FUNCTION_B, name: 'Marketing' },
          product_category: { id: TEST_PRODUCT_CATEGORY_B, name: 'Training' },
        },
      ],
      defaultValues: baseFormValues({
        product_lines: [
          { business_function_id: TEST_BUSINESS_FUNCTION_A, product_category_id: TEST_PRODUCT_CATEGORY_A },
          { business_function_id: TEST_BUSINESS_FUNCTION_B, product_category_id: TEST_PRODUCT_CATEGORY_B },
        ],
      }),
    })

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove product line' })[0])

    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Training'))
  })
})
