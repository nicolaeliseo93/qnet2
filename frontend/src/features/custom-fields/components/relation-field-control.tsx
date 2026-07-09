import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

function toNumberArray(value: unknown): number[] {
  return Array.isArray(value) ? value.filter((entry): entry is number => typeof entry === 'number') : []
}

/**
 * `type: 'relation'` → `AsyncPaginatedSelect` (cardinality `one`) or
 * `AsyncPaginatedMultiSelect` (`many`) against `relation.for_select_resource`.
 * No `selectedItem(s)` hydration prop is passed: both controls already
 * self-hydrate the label(s) for an id not on the current page via their `ids`
 * for-select param.
 */
export function RelationFieldControl({
  descriptor,
  value,
  onChange,
  disabled,
}: CustomFieldControlProps) {
  const { t } = useTranslation()
  const relation = descriptor.relation

  if (!relation) {
    return null
  }

  if (relation.cardinality === 'many') {
    return (
      <AsyncPaginatedMultiSelect
        resource={relation.for_select_resource}
        value={toNumberArray(value)}
        onChange={onChange}
        disabled={disabled}
        labels={{
          placeholder: descriptor.placeholder ?? t('customFields.relation.placeholder'),
          searchPlaceholder: t('customFields.relation.searchPlaceholder'),
          empty: t('customFields.relation.empty'),
          error: t('customFields.relation.error'),
          removeLabel: t('customFields.relation.removeLabel'),
          triggerLabel: descriptor.label,
          retry: t('common.retry'),
        }}
      />
    )
  }

  return (
    <AsyncPaginatedSelect
      resource={relation.for_select_resource}
      value={typeof value === 'number' ? value : null}
      onChange={onChange}
      disabled={disabled}
      labels={{
        placeholder: descriptor.placeholder ?? t('customFields.relation.placeholder'),
        searchPlaceholder: t('customFields.relation.searchPlaceholder'),
        empty: t('customFields.relation.empty'),
        error: t('customFields.relation.error'),
        clearLabel: t('customFields.relation.clearLabel'),
        triggerLabel: descriptor.label,
        retry: t('common.retry'),
      }}
    />
  )
}
