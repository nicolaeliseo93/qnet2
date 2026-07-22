/**
 * Readers for a column's `options`, which carry TWO shapes since spec 0055
 * D-2: plain scalars for enum/badge/tags columns (the set-filter and rich
 * editor catalogue) and `SelectOption` objects for an `editor: 'select'`
 * column, whose `value` is the id the PATCH submits.
 *
 * Every consumer narrows through one of these two functions instead of
 * asserting a shape: the wrong shape yields an empty list, never a
 * half-rendered option row.
 */
import type { SelectOption, TableColumn } from '@/features/table/types'

/** The SCALAR option list of an enum/badge/tags column. */
export function scalarColumnOptions(column: TableColumn | undefined): string[] {
  return (column?.options ?? []).filter((option): option is string => typeof option === 'string')
}

/** The OBJECT option list of an `editor: 'select'` column. */
export function selectColumnOptions(column: TableColumn | undefined): SelectOption[] {
  return (column?.options ?? []).filter(
    (option): option is SelectOption => typeof option === 'object' && option !== null,
  )
}
