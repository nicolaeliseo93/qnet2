/**
 * Separator joining product-category names into the opportunity's
 * auto-computed name (spec 0040 amendment rev.3, AC-107). Standalone pure
 * module (no RHF/feature imports) so it can be shared by both
 * `use-opportunity-product-lines` and `use-opportunity-lead-selection`
 * without a circular import through `use-opportunity-form`.
 */
export const PRODUCT_LINE_NAME_SEPARATOR = ' + '

/**
 * Composes the opportunity name from the product categories chosen across
 * every product-line row, in row order (AC-107). An unresolved label (still
 * being fetched) is skipped rather than rendered as a placeholder.
 */
export function composeProductLinesName(categoryNames: ReadonlyArray<string | undefined>): string {
  return categoryNames
    .filter((name): name is string => Boolean(name))
    .join(PRODUCT_LINE_NAME_SEPARATOR)
}
