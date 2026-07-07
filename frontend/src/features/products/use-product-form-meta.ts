import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProductFormMode } from '@/features/products/types'

/** Metadata-loading state driving what `ProductForm` renders. */
export type ProductFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004), covering
 * the GENERIC fields only — dynamic attribute values are authorized at the
 * resource level (`products.update`/`products.create`), not per field. Edit
 * mode seeds it from the loaded instance detail; create mode fetches
 * `GET /meta/products` once.
 */
export function useProductFormMeta(mode: ProductFormMode): ProductFormMetaState {
  const metaQuery = useResourceMeta('products', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.product.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
