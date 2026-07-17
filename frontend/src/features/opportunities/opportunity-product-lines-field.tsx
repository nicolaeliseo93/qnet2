import { useTranslation } from 'react-i18next'
import { Boxes, Plus, Trash2 } from 'lucide-react'
import type { UseFormSetValue } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import {
  useOpportunityProductLines,
  type OpportunityProductLineRow,
} from '@/features/opportunities/use-opportunity-product-lines'
import type { OpportunityNameAutofill } from '@/features/opportunities/use-opportunity-name-autofill'
import type { OpportunityProductLine } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityProductLinesFieldProps {
  /** The `product_lines` RHF field, as handed down by `MetaField`'s render prop (mirrors `ManagerSlotsField`). */
  value: OpportunityProductLineRow[]
  onChange: (next: OpportunityProductLineRow[]) => void
  setValue: UseFormSetValue<OpportunityFormValues>
  /** Rows whose labels are already known without a fetch (edit load, from-lead prefill, in-form Lead picker). */
  knownLines: OpportunityProductLine[]
  nameAutofill: OpportunityNameAutofill
  disabled?: boolean
}

/**
 * The opportunity's business-function + product-category rows (spec 0040
 * amendment rev.3, AC-106/107), styled and interacted with exactly like
 * `ManagerSlotsField`: "Add" appends an empty row (full-width dashed
 * button), each row edits its pair IN PLACE (numbered chip, two selects,
 * a trailing remove button), the category select is scoped to the row's
 * own function and disabled until it is chosen. All non-render logic
 * (label resolution, the name auto-fill) lives in `useOpportunityProductLines`
 * — this component only renders it.
 */
export function OpportunityProductLinesField({
  value,
  onChange,
  setValue,
  knownLines,
  nameAutofill,
  disabled = false,
}: OpportunityProductLinesFieldProps) {
  const { t } = useTranslation()
  const { addRow, removeRow, setRowBusinessFunction, setRowProductCategory, businessFunctionLabel, productCategoryLabel } =
    useOpportunityProductLines({ value, onChange, setValue, knownLines, nameAutofill })

  const selectLabels = {
    placeholder: t('opportunities.form.selectPlaceholder'),
    empty: t('opportunities.form.selectEmpty'),
    error: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retry: t('common.retry'),
  }

  return (
    <div className="flex flex-col gap-2">
      <ul className="flex flex-col gap-2">
        {value.map((row, index) => {
          const businessFunctionSelected =
            row.business_function_id !== null
              ? { id: row.business_function_id, label: businessFunctionLabel(row.business_function_id) ?? `#${row.business_function_id}` }
              : null
          const productCategorySelected =
            row.product_category_id !== null
              ? { id: row.product_category_id, label: productCategoryLabel(row.product_category_id) ?? `#${row.product_category_id}` }
              : null

          return (
            // The row's identity IS its position, so the index is the correct key (mirrors ManagerSlotsField).
            <li key={index} className="flex items-center gap-2">
              <span
                className="flex w-9 shrink-0 items-center gap-1 text-xs font-semibold text-muted-foreground"
                title={t('opportunities.form.productLines.rowLabel', { n: index + 1 })}
              >
                <Boxes aria-hidden="true" className="size-3.5" />
                {index + 1}
              </span>

              <div className="flex min-w-0 flex-1 gap-2">
                <div className="min-w-0 flex-1">
                  <AsyncPaginatedSelect
                    resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
                    value={row.business_function_id}
                    onChange={(id) => setRowBusinessFunction(index, id)}
                    selectedItem={businessFunctionSelected}
                    disabled={disabled}
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('opportunities.form.businessFunctionSearch'),
                      triggerLabel: t('opportunities.form.productLines.businessFunction', { n: index + 1 }),
                    }}
                  />
                </div>

                <div className="min-w-0 flex-1">
                  <AsyncPaginatedSelect
                    resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE}
                    value={row.product_category_id}
                    onChange={(id) => setRowProductCategory(index, id)}
                    selectedItem={productCategorySelected}
                    disabled={disabled || row.business_function_id === null}
                    params={row.business_function_id !== null ? { business_function_id: row.business_function_id } : undefined}
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('opportunities.form.productCategorySearch'),
                      triggerLabel: t('opportunities.form.productLines.category', { n: index + 1 }),
                    }}
                  />
                </div>
              </div>

              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('opportunities.form.productLines.remove')}
                  disabled={disabled}
                  onClick={() => removeRow(index)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            </li>
          )
        })}
      </ul>

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled}
        onClick={addRow}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('opportunities.form.productLines.add')}
      </Button>

      <p className="text-xs text-muted-foreground">{t('opportunities.form.productLines.hint')}</p>
    </div>
  )
}
