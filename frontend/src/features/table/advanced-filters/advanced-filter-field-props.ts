import type {
  AdvancedFilterDescriptor,
  AdvancedFilterValue,
} from '@/features/table/advanced-filters/types'

/**
 * Uniform controlled-component contract every entry of
 * `ADVANCED_FILTER_FIELD_REGISTRY` implements (mirrors the custom-fields
 * `CustomFieldControlProps` pattern). Kept in its own module so a field
 * component imports just the type, not the registry itself.
 */
export interface AdvancedFilterFieldProps {
  descriptor: AdvancedFilterDescriptor
  value: AdvancedFilterValue
  onChange: (value: AdvancedFilterValue) => void
  /** Disabled because its `dependency.on` parent has no value, or the panel is applying. */
  disabled: boolean
  /** Accessible-error triad (frontend.md §10), built by `AdvancedFilterPanel`. */
  id: string
  describedBy?: string
  invalid: boolean
  /**
   * Extra for-select query parameters resolved from an active
   * `dependency.param` (relation/autocomplete/async_search only): the
   * parent filter's current value, forwarded so the child's option list is
   * scoped by it.
   */
  dependencyParams?: Record<string, string | number>
}
