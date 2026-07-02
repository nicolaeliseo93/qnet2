import type { OwnerRef } from '@/features/personal-data/types'

/**
 * Query keys for the personal-data module. The card (with its contacts and
 * addresses) is cached per owner, so any owner type shares the same structure.
 */
export const personalDataKeys = {
  all: ['personal-data'] as const,
  byOwner: (owner: OwnerRef) =>
    ['personal-data', 'by-owner', owner.type, owner.id] as const,
}
