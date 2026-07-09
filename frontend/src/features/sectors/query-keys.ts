/** Centralized TanStack Query keys for the sectors domain. */
export const sectorKeys = {
  tree: ['sectors', 'tree'] as const,
  detail: (id: number) => ['sectors', 'detail', id] as const,
}
