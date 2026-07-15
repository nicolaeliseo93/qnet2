import type { ComponentType } from 'react'
import { createDateAdvancedFilterField } from '@/features/table/advanced-filters/fields/date-native-field'
import { DateRangeAdvancedFilterField } from '@/features/table/advanced-filters/fields/date-range-field'
import {
  CheckboxAdvancedFilterField,
  EnumAdvancedFilterField,
  MultiSelectAdvancedFilterField,
  RadioAdvancedFilterField,
  SelectAdvancedFilterField,
  SwitchAdvancedFilterField,
} from '@/features/table/advanced-filters/fields/choice-fields'
import {
  AsyncSearchAdvancedFilterField,
  AutocompleteAdvancedFilterField,
  AutocompleteMultiAdvancedFilterField,
  RelationAdvancedFilterField,
} from '@/features/table/advanced-filters/fields/relation-fields'
import {
  NumberAdvancedFilterField,
  NumberRangeAdvancedFilterField,
  TextAdvancedFilterField,
  TextareaAdvancedFilterField,
} from '@/features/table/advanced-filters/fields/text-fields'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'
import type { AdvancedFilterType } from '@/features/table/advanced-filters/types'

export type { AdvancedFilterFieldProps }

/**
 * The single seam mapping a backend `AdvancedFilterType` to a frontend field
 * component (spec 0032 AC-011, OCP §3 of engineering.md). Adding a new type
 * means: 1) a new field component in `fields/`; 2) one new entry below.
 * `AdvancedFilterPanel` never branches on `type` itself — it only looks up
 * this map.
 */
export const ADVANCED_FILTER_FIELD_REGISTRY: Record<
  AdvancedFilterType,
  ComponentType<AdvancedFilterFieldProps>
> = {
  text: TextAdvancedFilterField,
  textarea: TextareaAdvancedFilterField,
  number: NumberAdvancedFilterField,
  number_range: NumberRangeAdvancedFilterField,
  date: createDateAdvancedFilterField('date'),
  date_range: DateRangeAdvancedFilterField,
  datetime: createDateAdvancedFilterField('datetime-local'),
  select: SelectAdvancedFilterField,
  multiselect: MultiSelectAdvancedFilterField,
  autocomplete: AutocompleteAdvancedFilterField,
  autocomplete_multi: AutocompleteMultiAdvancedFilterField,
  checkbox: CheckboxAdvancedFilterField,
  switch: SwitchAdvancedFilterField,
  radio: RadioAdvancedFilterField,
  enum: EnumAdvancedFilterField,
  relation: RelationAdvancedFilterField,
  async_search: AsyncSearchAdvancedFilterField,
}
