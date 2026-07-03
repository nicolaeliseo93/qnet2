/** Query keys for the authorization metadata module. */
export const metaKeys = {
  all: ['meta'] as const,
  resource: (resource: string) => ['meta', resource] as const,
}
