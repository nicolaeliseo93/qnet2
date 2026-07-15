import { useTranslation } from 'react-i18next'
import {
  AsyncPaginatedMultiSelect,
  type AsyncPaginatedMultiSelectLabels,
} from '@/components/ui/async-paginated-multi-select'
import {
  AsyncPaginatedSelect,
  type AsyncPaginatedSelectLabels,
} from '@/components/ui/async-paginated-select'
import { toOptionValueArray } from '@/features/table/advanced-filters/option-utils'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'

/**
 * Single-select for-select field, shared by `autocomplete`, `async_search`
 * and `relation` (cardinality `one`). `descriptor.source.resource` is
 * resolved at RUNTIME from the backend catalog (spec 0032 AC-011); a
 * `dependency.param` forwards the parent value as an extra for-select query
 * parameter, scoping the child's options.
 */
function SingleRelationField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
  dependencyParams,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const labels: AsyncPaginatedSelectLabels = {
    placeholder: descriptor.placeholder
      ? t(descriptor.placeholder)
      : t('table.advancedFilters.selectPlaceholder'),
    searchPlaceholder: t('table.advancedFilters.searchPlaceholder'),
    empty: t('table.advancedFilters.empty'),
    error: t('table.advancedFilters.loadError'),
    clearLabel: t('table.advancedFilters.clearLabel'),
    triggerLabel: t(descriptor.label),
    retry: t('common.retry'),
  }

  return (
    <AsyncPaginatedSelect
      resource={descriptor.source?.resource ?? ''}
      value={typeof value === 'number' ? value : null}
      onChange={onChange}
      disabled={disabled}
      id={id}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      params={dependencyParams}
      labels={labels}
    />
  )
}

/** Multi-select for-select field, shared by `autocomplete_multi` and `relation` (cardinality `many`). */
function MultiRelationField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
  dependencyParams,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const labels: AsyncPaginatedMultiSelectLabels = {
    placeholder: descriptor.placeholder
      ? t(descriptor.placeholder)
      : t('table.advancedFilters.selectPlaceholder'),
    searchPlaceholder: t('table.advancedFilters.searchPlaceholder'),
    empty: t('table.advancedFilters.empty'),
    error: t('table.advancedFilters.loadError'),
    removeLabel: t('table.advancedFilters.removeLabel'),
    triggerLabel: t(descriptor.label),
    retry: t('common.retry'),
  }

  return (
    <AsyncPaginatedMultiSelect
      resource={descriptor.source?.resource ?? ''}
      value={toOptionValueArray(value).filter(
        (entry): entry is number => typeof entry === 'number',
      )}
      onChange={onChange}
      disabled={disabled}
      id={id}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      params={dependencyParams}
      labels={labels}
    />
  )
}

/** `type: 'autocomplete'` -> always single-valued. */
export function AutocompleteAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <SingleRelationField {...props} />
}

/** `type: 'autocomplete_multi'` -> always multi-valued. */
export function AutocompleteMultiAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <MultiRelationField {...props} />
}

/**
 * `type: 'async_search'` -> always single-valued, functionally identical to
 * `autocomplete` (spec 0032 AC-011 groups them under the same component).
 */
export function AsyncSearchAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <SingleRelationField {...props} />
}

/** `type: 'relation'` -> cardinality driven by `descriptor.multiple`. */
export function RelationAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return props.descriptor.multiple ? (
    <MultiRelationField {...props} />
  ) : (
    <SingleRelationField {...props} />
  )
}
