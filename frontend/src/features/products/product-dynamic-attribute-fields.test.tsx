import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductDynamicAttributeFields } from '@/features/products/product-dynamic-attribute-fields'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { AttributeFieldValue } from '@/features/products/types'

/** Spec 0017 AC-023: dynamic fields generated per data type, regenerated on category change. */

const useEffectiveAttributesMock = vi.fn()

vi.mock('@/features/product-categories/use-effective-attributes', () => ({
  useEffectiveAttributes: (categoryId: number | null) => useEffectiveAttributesMock(categoryId),
}))

const LAPTOPS_ATTRIBUTES: EffectiveAttribute[] = [
  { id: 1, code: 'material', name: 'Material', data_type: 'STRING', is_required: false, inherited: false, options: [] },
  { id: 2, code: 'ram_gb', name: 'RAM (GB)', data_type: 'INTEGER', is_required: true, inherited: false, options: [] },
  { id: 3, code: 'has_touch', name: 'Touchscreen', data_type: 'BOOLEAN', is_required: false, inherited: false, options: [] },
  {
    id: 4,
    code: 'color',
    name: 'Color',
    data_type: 'ENUM',
    is_required: true,
    inherited: true,
    options: [
      { value: 'silver', label: 'Silver' },
      { value: 'black', label: 'Black' },
    ],
  },
]

const CHAIRS_ATTRIBUTES: EffectiveAttribute[] = [
  { id: 5, code: 'seat_material', name: 'Seat material', data_type: 'STRING', is_required: false, inherited: false, options: [] },
]

function queryResultFor(data: EffectiveAttribute[]) {
  return { data, isPending: false, isError: false, refetch: vi.fn() }
}

/** Controlled harness so the test can drive the `value`/`onChange` contract like the real form. */
function Harness({ categoryId }: { categoryId: number | null }) {
  const [value, setValue] = useState<Record<string, AttributeFieldValue>>({})
  return <ProductDynamicAttributeFields categoryId={categoryId} value={value} onChange={setValue} />
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useEffectiveAttributesMock.mockReset()
})

describe('ProductDynamicAttributeFields — typed generation (AC-023)', () => {
  it('renders one control per data type: text, number, checkbox, select', async () => {
    useEffectiveAttributesMock.mockReturnValue(queryResultFor(LAPTOPS_ATTRIBUTES))

    render(<Harness categoryId={1} />)

    expect(await screen.findByLabelText(/Material/)).toHaveAttribute('type', 'text')
    expect(screen.getByLabelText(/RAM \(GB\)/)).toHaveAttribute('type', 'number')
    expect(screen.getByRole('checkbox', { name: /Touchscreen/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /Color/ })).toBeInTheDocument()
  })

  it('marks a required attribute and an inherited one', async () => {
    useEffectiveAttributesMock.mockReturnValue(queryResultFor(LAPTOPS_ATTRIBUTES))

    render(<Harness categoryId={1} />)

    expect(await screen.findByText(/RAM \(GB\) \*/)).toBeInTheDocument()
    expect(screen.getByText('Inherited')).toBeInTheDocument()
  })

  it('regenerates the fields when the category changes', async () => {
    useEffectiveAttributesMock.mockImplementation((categoryId: number | null) =>
      queryResultFor(categoryId === 1 ? LAPTOPS_ATTRIBUTES : CHAIRS_ATTRIBUTES),
    )

    const { rerender } = render(<Harness categoryId={1} />)
    expect(await screen.findByLabelText(/Material/)).toBeInTheDocument()

    rerender(<Harness categoryId={2} />)

    await waitFor(() => expect(screen.getByLabelText(/Seat material/)).toBeInTheDocument())
    expect(screen.queryByLabelText(/RAM \(GB\)/)).not.toBeInTheDocument()
  })
})
