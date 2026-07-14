import { describe, expect, it } from 'vitest'
import { moduleStats as moduleStatsEn } from '@/i18n/locales/en-stats'
import { moduleStats as moduleStatsIt } from '@/i18n/locales/it-stats'

/**
 * Safety net for the backend-driven contract (spec 0026 D-4): a widget
 * `label`/subtitle `key` sent by the backend is resolved with `t(key)`
 * against whichever locale is active. A key present in one locale's
 * `moduleStats` but missing in the other would silently render the RAW key
 * on screen for that locale instead of text — this test fails the build
 * before that ever reaches a user.
 */

type NestedStrings = { [key: string]: string | NestedStrings }

/** Collects every leaf key as a dot-path (e.g. `leads.conversionRateSubtitle_one`). */
function collectKeyPaths(node: NestedStrings, prefix = ''): string[] {
  return Object.entries(node).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [path] : collectKeyPaths(value, path)
  })
}

describe('moduleStats — en/it key parity', () => {
  const enKeys = collectKeyPaths(moduleStatsEn).sort()
  const itKeys = collectKeyPaths(moduleStatsIt).sort()

  it('has no key present in en but missing in it', () => {
    const missingInIt = enKeys.filter((key) => !itKeys.includes(key))
    expect(missingInIt).toEqual([])
  })

  it('has no key present in it but missing in en', () => {
    const missingInEn = itKeys.filter((key) => !enKeys.includes(key))
    expect(missingInEn).toEqual([])
  })

  it('never leaves a blank translation for either locale', () => {
    const blankEn = collectKeyPaths(moduleStatsEn).filter((path) => resolve(moduleStatsEn, path).trim() === '')
    const blankIt = collectKeyPaths(moduleStatsIt).filter((path) => resolve(moduleStatsIt, path).trim() === '')

    expect(blankEn).toEqual([])
    expect(blankIt).toEqual([])
  })

  it('exposes the module 4-counter widgets added in this round (en + it)', () => {
    const addedKeys = [
      'registries.agreed',
      'referents.assigned',
      'companies.withSites',
      'companies.sites',
      'operationalSites.withAddress',
      'operationalSites.staffed',
      'operationalSites.leads',
      'companySites.withBank',
      'companySites.companies',
      'products.averageCost',
      'leads.assigned',
      'businessFunctions.withManager',
      'productCategories.withProducts',
      'productCategories.inheritsAttributes',
      'users.managers',
    ]

    for (const key of addedKeys) {
      expect(enKeys, `en is missing ${key}`).toContain(key)
      expect(itKeys, `it is missing ${key}`).toContain(key)
    }
  })

  it('dropped the removed `projects.totalBudget` widget in both locales', () => {
    expect(enKeys).not.toContain('projects.totalBudget')
    expect(itKeys).not.toContain('projects.totalBudget')
  })
})

function resolve(node: NestedStrings, path: string): string {
  const value = path.split('.').reduce<NestedStrings | string>((acc, segment) => {
    if (typeof acc === 'string') {
      return acc
    }
    return acc[segment]
  }, node)

  return typeof value === 'string' ? value : ''
}
