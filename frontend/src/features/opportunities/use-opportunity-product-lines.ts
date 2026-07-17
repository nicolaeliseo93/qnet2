import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { UseFormSetValue } from 'react-hook-form'
import { fetchForSelect } from '@/features/for-select/api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { composeProductLinesName } from '@/features/opportunities/opportunity-product-line-name'
import type { OpportunityNameAutofill } from '@/features/opportunities/use-opportunity-name-autofill'
import type { OpportunityProductLine } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** One `product_lines` row as edited inline (either id may still be empty while the row is being filled). */
export type OpportunityProductLineRow = OpportunityFormValues['product_lines'][number]

type LabelMap = Record<number, string>

interface UseOpportunityProductLinesArgs {
  /** The `product_lines` RHF field's current value (from `MetaField`'s render prop, mirrors `ManagerSlotsField`). */
  value: OpportunityProductLineRow[]
  onChange: (next: OpportunityProductLineRow[]) => void
  setValue: UseFormSetValue<OpportunityFormValues>
  /** Rows whose labels are already known without a fetch: edit load, from-lead prefill, in-form Lead picker. */
  knownLines: OpportunityProductLine[]
  nameAutofill: OpportunityNameAutofill
}

/** Builds `{id: name}` lookup maps out of a set of already-labeled lines. */
function indexKnownLabels(lines: OpportunityProductLine[]): { businessFunction: LabelMap; productCategory: LabelMap } {
  const businessFunction: LabelMap = {}
  const productCategory: LabelMap = {}
  for (const line of lines) {
    businessFunction[line.business_function.id] = line.business_function.name
    productCategory[line.product_category.id] = line.product_category.name
  }
  return { businessFunction, productCategory }
}

/**
 * Owns the opportunity's inline product-lines row editor (spec 0040
 * amendment rev.3, AC-106): "Add" appends an EMPTY row (mirrors
 * `manager_slots`' "Add slot"), each row is edited IN PLACE — picking a
 * business function resets that row's category (still scoped by it,
 * AC-104) — and a row is removed outright. Labels come from two sources,
 * merged: `knownLines` (already hydrated, computed fresh every render —
 * cheap, no fetch) and a locally-fetched cache for whatever the user picks
 * in a row (a single one-shot lookup by id, run as a direct consequence of
 * the user's `onChange`, never a render-time effect — mirrors every other
 * BR-4 prefill in this feature, e.g. `opportunity-registry-field.tsx`).
 */
export function useOpportunityProductLines({
  value,
  onChange,
  setValue,
  knownLines,
  nameAutofill,
}: UseOpportunityProductLinesArgs) {
  const queryClient = useQueryClient()
  const [fetchedBusinessFunctionLabels, setFetchedBusinessFunctionLabels] = useState<LabelMap>({})
  const [fetchedProductCategoryLabels, setFetchedProductCategoryLabels] = useState<LabelMap>({})

  const known = indexKnownLabels(knownLines)

  const businessFunctionLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (known.businessFunction[id] ?? fetchedBusinessFunctionLabels[id])
  const productCategoryLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (known.productCategory[id] ?? fetchedProductCategoryLabels[id])

  /** Resolves an id's label; returns it directly (not just via the state setter) so a caller can use it immediately, before the next render. */
  const resolveLabel = async (
    resource: string,
    id: number,
    setLabels: (updater: (previous: LabelMap) => LabelMap) => void,
  ): Promise<string | undefined> => {
    const page = await queryClient.fetchQuery({
      queryKey: ['opportunities', 'product-line-label', resource, id],
      queryFn: () => fetchForSelect(resource, { ids: [id] }),
    })
    const label = page.items.find((item) => item.id === id)?.label
    if (label !== undefined) {
      setLabels((previous) => ({ ...previous, [id]: label }))
    }
    return label
  }

  /**
   * Recomputes the name from every row's resolved category label, in row
   * order, unless already hand-edited. `resolveCategoryLabel` defaults to
   * the closure's own `productCategoryLabel`, but a caller resolving a label
   * asynchronously (below) overrides it with the value it just fetched —
   * `productCategoryLabel` itself would still be stale at that point (it
   * closed over this render's `fetchedProductCategoryLabels`, not the one
   * the pending `setLabels` call is about to produce).
   */
  const applyNameAutofill = (
    rows: OpportunityProductLineRow[],
    resolveCategoryLabel: (id: number | null) => string | undefined = productCategoryLabel,
  ) => {
    if (!nameAutofill.isAuto()) {
      return
    }
    setValue('name', composeProductLinesName(rows.map((row) => resolveCategoryLabel(row.product_category_id))), {
      shouldDirty: true,
    })
  }

  const addRow = () => {
    onChange([...value, { business_function_id: null, product_category_id: null }])
  }

  const removeRow = (index: number) => {
    const next = value.filter((_, rowIndex) => rowIndex !== index)
    onChange(next)
    applyNameAutofill(next)
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
    applyNameAutofill(next)
  }

  const setRowProductCategory = (index: number, productCategoryId: number | null) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: productCategoryId } : row,
    )
    onChange(next)

    if (productCategoryId !== null && productCategoryLabel(productCategoryId) === undefined) {
      // The label for THIS id is not known yet (a fresh pick): recompute the
      // name once it resolves, substituting the freshly-fetched value in for
      // just that id (see `applyNameAutofill`'s doc) rather than waiting for
      // a re-render that never re-triggers this computation on its own.
      void resolveLabel(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE, productCategoryId, setFetchedProductCategoryLabels).then(
        (resolvedLabel) => {
          applyNameAutofill(next, (id) => (id === productCategoryId ? resolvedLabel : productCategoryLabel(id)))
        },
      )
      return
    }

    applyNameAutofill(next)
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
