import { useTranslation } from 'react-i18next'
import type { Control, UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { fetchOpportunityProductCategoryMeta } from '@/features/opportunities/opportunity-relation-meta'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityProductCategoryFieldProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  getValues: UseFormGetValues<OpportunityFormValues>
  /** The loaded opportunity's linked product category, if any (edit mode hydration). */
  selected: RelationFieldRef | null
  /** BR-2: forced read-only when derived from a linked Lead (spec 0040 MT-6). */
  forceDisabled?: boolean
}

function toForSelectItem(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * The opportunity's optional product category. BR-4: picking one prefills
 * the business function field with the category's own effective (own-or-
 * inherited) function (`meta.business_function`), but ONLY when that field is
 * still empty — an already-chosen function is never silently overwritten. A
 * single one-shot fetch, run as a direct consequence of the user's selection.
 */
export function OpportunityProductCategoryField({
  control,
  setValue,
  getValues,
  selected,
  forceDisabled = false,
}: OpportunityProductCategoryFieldProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { quickCreated, renderAction } = useQuickCreateAction(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE)

  const applyProductCategorySelection = async (categoryId: number | null) => {
    if (categoryId === null || getValues('business_function_id') !== null) {
      return
    }
    const meta = await fetchOpportunityProductCategoryMeta(queryClient, categoryId)
    if (!meta?.business_function) {
      return
    }
    setValue('business_function_id', meta.business_function.id, { shouldDirty: true })
  }

  const selectCategory = (field: { onChange: (value: number | null) => void }, categoryId: number | null) => {
    field.onChange(categoryId)
    void applyProductCategorySelection(categoryId)
  }

  return (
    <MetaField
      control={control}
      name="product_category_id"
      metaKey="product_category_id"
      label={t('opportunities.form.productCategory')}
    >
      {({ field, disabled }) => {
        const isDisabled = disabled || forceDisabled
        const quickCreatedMatch = quickCreated.find((ref) => ref.id === field.value) ?? null
        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(categoryId) => selectCategory(field, categoryId)}
              selectedItem={toForSelectItem(quickCreatedMatch) ?? toForSelectItem(selected)}
              disabled={isDisabled}
              labels={{
                placeholder: t('opportunities.form.selectPlaceholder'),
                searchPlaceholder: t('opportunities.form.productCategorySearch'),
                empty: t('opportunities.form.selectEmpty'),
                error: t('opportunities.form.selectError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('opportunities.form.productCategory'),
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => selectCategory(field, ref.id), isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}
