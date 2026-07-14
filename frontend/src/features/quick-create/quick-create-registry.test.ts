import { describe, expect, it } from 'vitest'
import { resolveQuickCreate } from '@/features/quick-create/quick-create-registry'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { PROJECTS_FOR_SELECT_RESOURCE } from '@/features/projects/for-select-api'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'

/** Spec 0028 — resolveQuickCreate is the single derivation point for the "+". */
describe('resolveQuickCreate', () => {
  it('resolves a known async-module resource to its entry (AC-001 wiring)', () => {
    const entry = resolveQuickCreate(SOURCES_FOR_SELECT_RESOURCE)

    expect(entry).not.toBeNull()
    expect(entry?.permission).toBe('sources.create')
    expect(entry?.titleKey).toBe('sources.form.createTitle')
    expect(entry?.descriptionKey).toBe('sources.form.createSubtitle')
  })

  it('resolves every resource named in the contract, including the advanced ones', () => {
    expect(resolveQuickCreate(PROJECTS_FOR_SELECT_RESOURCE)?.permission).toBe('projects.create')
    expect(resolveQuickCreate(ROLES_FOR_SELECT_RESOURCE)?.permission).toBe('roles.create')
  })

  it('returns null for the geo resource, out of scope by decision D3 (AC-011)', () => {
    expect(resolveQuickCreate(STATES_FOR_SELECT_RESOURCE)).toBeNull()
  })

  it('returns null for a resource unknown to the registry, without throwing (AC-011)', () => {
    expect(() => resolveQuickCreate('not-a-real-resource')).not.toThrow()
    expect(resolveQuickCreate('not-a-real-resource')).toBeNull()
  })
})
