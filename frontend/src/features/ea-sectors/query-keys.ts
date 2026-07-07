/** Centralized TanStack Query keys for the ea-sectors domain. */
export const eaSectorKeys = {
  tree: ['ea-sectors', 'tree'] as const,
  detail: (id: number) => ['ea-sectors', 'detail', id] as const,
}
