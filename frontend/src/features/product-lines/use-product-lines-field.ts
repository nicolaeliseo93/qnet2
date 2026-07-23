import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

type LabelMap = Record<number, string>

interface UseProductLinesFieldArgs {
  /** The `product_lines` field's current value (RHF or plain state, mirrors `ManagerSlotsField`). */
  value: ProductLineRow[]
  onChange: (next: ProductLineRow[]) => void
  /** Rows whose labels are already known without a fetch: edit load, from-lead prefill, in-form pickers. */
  knownLines: ProductLine[]
}

/** Builds `{id: name}` lookup maps out of a set of already-labeled lines. */
function indexKnownLabels(lines: ProductLine[]): { businessFunction: LabelMap; productCategory: LabelMap } {
  const businessFunction: LabelMap = {}
  const productCategory: LabelMap = {}
  for (const line of lines) {
    businessFunction[line.business_function.id] = line.business_function.name
    productCategory[line.product_category.id] = line.product_category.name
  }
  return { businessFunction, productCategory }
}

/**
 * Owns the inline product-lines row editor (spec 0040 amendment rev.3
 * AC-106, generalized in spec 0057 for reuse outside the opportunity form):
 * "Add" appends an EMPTY row (mirrors `manager_slots`' "Add slot"), each row
 * is edited IN PLACE — picking a business function resets that row's
 * category (still scoped by it, AC-104) — and a row is removed outright.
 * Labels come from two sources, merged: `knownLines` (already hydrated,
 * computed fresh every render — cheap, no fetch) and a locally-fetched cache
 * for whatever the user picks in a row (a single one-shot lookup by id, run
 * as a direct consequence of the user's `onChange`, never a render-time
 * effect).
 */
export function useProductLinesField({ value, onChange, knownLines }: UseProductLinesFieldArgs) {
  const queryClient = useQueryClient()
  const [fetchedBusinessFunctionLabels, setFetchedBusinessFunctionLabels] = useState<LabelMap>({})
  const [fetchedProductCategoryLabels, setFetchedProductCategoryLabels] = useState<LabelMap>({})

  const known = indexKnownLabels(knownLines)

  const businessFunctionLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (known.businessFunction[id] ?? fetchedBusinessFunctionLabels[id])
  const productCategoryLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (known.productCategory[id] ?? fetchedProductCategoryLabels[id])

  /** Resolves an id's label; returns it directly so a caller can use it immediately, before the next render. */
  const resolveLabel = async (
    resource: string,
    id: number,
    setLabels: (updater: (previous: LabelMap) => LabelMap) => void,
  ): Promise<string | undefined> => {
    const page = await queryClient.fetchQuery({
      queryKey: ['product-lines', 'label', resource, id],
      queryFn: () => fetchForSelect(resource, { ids: [id] }),
    })
    const label = page.items.find((item) => item.id === id)?.label
    if (label !== undefined) {
      setLabels((previous) => ({ ...previous, [id]: label }))
    }
    return label
  }

  const addRow = () => {
    onChange([...value, { business_function_id: null, product_category_id: null }])
  }

  const removeRow = (index: number) => {
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  const setRowBusinessFunction = (index: number, businessFunctionId: number | null) => {
    // Changing the function invalidates whatever category was chosen for the previous one (AC-104 scoping).
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { business_function_id: businessFunctionId, product_category_id: null } : row,
    )
    onChange(next)
    if (businessFunctionId !== null && businessFunctionLabel(businessFunctionId) === undefined) {
      void resolveLabel(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, businessFunctionId, setFetchedBusinessFunctionLabels)
    }
  }

  const setRowProductCategory = (index: number, productCategoryId: number | null) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: productCategoryId } : row,
    )
    onChange(next)
    if (productCategoryId !== null && productCategoryLabel(productCategoryId) === undefined) {
      void resolveLabel(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE, productCategoryId, setFetchedProductCategoryLabels)
    }
  }

  return {
    addRow,
    removeRow,
    setRowBusinessFunction,
    setRowProductCategory,
    businessFunctionLabel,
    productCategoryLabel,
  }
}
