import type { EaSectorTreeNode } from '@/features/ea-sectors/types'

/** A flattened tree node, ready to feed a plain `id`/`name` select. */
export interface FlatEaSectorOption {
  id: number
  name: string
}

/** Indentation glyph prepended per depth level, so hierarchy reads at a glance in a flat list. */
const DEPTH_PREFIX = '    '

/**
 * Flattens the sector tree into a depth-first, indented option list for the
 * create/edit form's parent picker (`SearchableSelect`): no dedicated
 * `for-select` endpoint exists for sectors, so the already-fetched tree is
 * reused client-side instead of a new backend call.
 */
export function flattenEaSectorTree(
  nodes: EaSectorTreeNode[],
  depth = 0,
): FlatEaSectorOption[] {
  return nodes.flatMap((node) => [
    { id: node.id, name: `${DEPTH_PREFIX.repeat(depth)}${depth > 0 ? '↳ ' : ''}${node.name}` },
    ...flattenEaSectorTree(node.children, depth + 1),
  ])
}

/** Collects every descendant id of `nodeId` (inclusive), used to forbid picking a cyclic parent client-side. */
export function collectSubtreeIds(nodes: EaSectorTreeNode[], nodeId: number): Set<number> {
  const ids = new Set<number>()

  function visit(candidates: EaSectorTreeNode[], collecting: boolean) {
    for (const node of candidates) {
      const isTarget = collecting || node.id === nodeId
      if (isTarget) {
        ids.add(node.id)
      }
      visit(node.children, isTarget)
    }
  }

  visit(nodes, false)
  return ids
}
