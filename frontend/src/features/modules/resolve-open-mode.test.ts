import { describe, expect, it } from 'vitest'
import { resolveOpenMode } from '@/features/modules/resolve-open-mode'
import type { ModuleOpenPreferences } from '@/features/modules/types'

describe('resolveOpenMode', () => {
  it('AC-010: mode "modal" always resolves to modal, regardless of overrides/defaultMode', () => {
    const prefs: ModuleOpenPreferences = { mode: 'modal', overrides: { projects: 'page' } }
    expect(resolveOpenMode(prefs, 'projects', 'page')).toBe('modal')
  })

  it('AC-010: mode "page" always resolves to page, regardless of overrides/defaultMode', () => {
    const prefs: ModuleOpenPreferences = { mode: 'page', overrides: { projects: 'modal' } }
    expect(resolveOpenMode(prefs, 'projects', 'modal')).toBe('page')
  })

  it('AC-010: mode "custom" with an override for the domain resolves to that override', () => {
    const prefs: ModuleOpenPreferences = {
      mode: 'custom',
      overrides: { projects: 'page', campaigns: 'modal' },
    }
    expect(resolveOpenMode(prefs, 'projects', 'modal')).toBe('page')
    expect(resolveOpenMode(prefs, 'campaigns', 'page')).toBe('modal')
  })

  it('AC-010: mode "custom" without an override for the domain falls back to defaultMode', () => {
    const prefs: ModuleOpenPreferences = { mode: 'custom', overrides: {} }
    expect(resolveOpenMode(prefs, 'leads', 'modal')).toBe('modal')
    expect(resolveOpenMode(prefs, 'opportunities', 'page')).toBe('page')
  })

  it('AC-010: a null/undefined preference (never set) falls back to defaultMode', () => {
    expect(resolveOpenMode(null, 'projects', 'modal')).toBe('modal')
    expect(resolveOpenMode(undefined, 'projects', 'page')).toBe('page')
  })
})
