import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import type { AttributeCatalogEntry } from '@/features/attributes/use-attribute-catalog'

/**
 * Task #19: the assigned-attribute row must be self-explanatory — a helper
 * blurb above the list, and an info tooltip on `is_required`/`sort_order`/the
 * data-type badge explaining what each one does.
 */

const useAttributeCatalogMock = vi.fn()

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => useAttributeCatalogMock(),
}))

const CATALOG: AttributeCatalogEntry[] = [
  { id: 1, code: 'color', name: 'Color', data_type: 'ENUM' },
  { id: 2, code: 'ram_gb', name: 'RAM (GB)', data_type: 'INTEGER' },
]

function queryResult(data: AttributeCatalogEntry[] = CATALOG) {
  return { data, isPending: false, isError: false, refetch: vi.fn() }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useAttributeCatalogMock.mockReset()
  useAttributeCatalogMock.mockReturnValue(queryResult())
})

describe('AttributeAssignmentEditor — self-explanatory row (task #19)', () => {
  it('shows the section helper text', () => {
    render(
      <AttributeAssignmentEditor value={[]} onChange={vi.fn()} inherited={[]} />,
    )

    expect(
      screen.getByText(
        'Assign the attributes that products in this category must fill in (in addition to what they inherit).',
      ),
    ).toBeInTheDocument()
  })

  it('shows a visible "Order" label next to the sort_order input', () => {
    render(
      <AttributeAssignmentEditor
        value={[{ attribute_id: 1, is_required: false, sort_order: 0 }]}
        onChange={vi.fn()}
        inherited={[]}
      />,
    )

    expect(screen.getByText('Order')).toBeInTheDocument()
    expect(screen.getByLabelText('Order')).toBeInTheDocument()
  })

  it('reveals what "Required" means in a tooltip', async () => {
    render(
      <AttributeAssignmentEditor
        value={[{ attribute_id: 1, is_required: false, sort_order: 0 }]}
        onChange={vi.fn()}
        inherited={[]}
      />,
    )

    fireEvent.focus(screen.getByLabelText('When on, the product MUST fill in this attribute.'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(
        'When on, the product MUST fill in this attribute.',
      )
    })
  })

  it('reveals what "Order" means in a tooltip', async () => {
    render(
      <AttributeAssignmentEditor
        value={[{ attribute_id: 1, is_required: false, sort_order: 0 }]}
        onChange={vi.fn()}
        inherited={[]}
      />,
    )

    fireEvent.focus(
      screen.getByLabelText('The position this field appears at in the product form.'),
    )
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(
        'The position this field appears at in the product form.',
      )
    })
  })

  it('describes the ENUM data type in a tooltip on the badge', async () => {
    render(
      <AttributeAssignmentEditor
        value={[{ attribute_id: 1, is_required: false, sort_order: 0 }]}
        onChange={vi.fn()}
        inherited={[]}
      />,
    )

    fireEvent.focus(screen.getByText('List of options'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(
        'A choice from a fixed list of options.',
      )
    })
  })
})
