/** Centralized TanStack Query keys for the aggregated activity log (spec 0034). */
export const activityLogKeys = {
  list: (resource: string, id: number) => ['activity-log', resource, id] as const,
}
