import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors `CategoryHierarchy`'s own walk-depth guard (backend, spec 0023): defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/** The business function a candidate parent would make a category inherit, plus its direct source. */
export interface InheritedBusinessFunction {
  businessFunctionId: number
  sourceCategory: { id: number; name: string }
}

/** Flat `id -> node` lookup over the whole cached tree, for parent-chain walks. */
export function indexCategoryTree(nodes: ProductCategoryTreeNode[]): Map<number, ProductCategoryTreeNode> {
  const index = new Map<number, ProductCategoryTreeNode>()

  function visit(candidates: ProductCategoryTreeNode[]) {
    for (const node of candidates) {
      index.set(node.id, node)
      visit(node.children)
    }
  }

  visit(nodes)
  return index
}

/**
 * Resolves the business function a category would inherit from `parentId`
 * (spec 0023 AC-019): walks `parent_id` up the cached tree, transitively,
 * and returns the first ancestor's OWN business function found — or null
 * when no ancestor has one (including `parentId === null`, a root pick).
 * Client-side mirror of the backend's `CategoryHierarchy` walk, using only
 * the tree's own `business_function_id` per node (spec 0023 AC-018); the
 * backend PATCH/POST 422 remains the authority for the actual write.
 */
export function resolveInheritedBusinessFunction(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedBusinessFunction | null {
  let currentId = parentId
  let depth = 0

  while (currentId !== null && depth < MAX_DEPTH) {
    const node = nodesById.get(currentId)
    if (!node) {
      return null
    }
    if (node.business_function_id !== null) {
      return { businessFunctionId: node.business_function_id, sourceCategory: { id: node.id, name: node.name } }
    }
    currentId = node.parent_id
    depth += 1
  }

  return null
}
