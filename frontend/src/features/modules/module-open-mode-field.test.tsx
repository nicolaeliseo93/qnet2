import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ModuleOpenModeField } from '@/features/modules/module-open-mode-field'
import { MODULE_REGISTRY } from '@/features/modules/module-registry'
import { DEFAULT_MODULE_OPEN_PREFERENCES, type ModuleOpenPreferences } from '@/features/modules/types'

/**
 * AC-013/014/016: the settings control shows a 3-state mode selector and, in
 * custom mode, one open-mode selector per registered module; overrides survive
 * a switch to a global mode (they reappear when custom is selected again).
 */

function renderField(value: ModuleOpenPreferences) {
  return render(<ModuleOpenModeField value={value} onChange={vi.fn()} />)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ModuleOpenModeField', () => {
  it('AC-013: renders the section heading and the mode selector', () => {
    renderField(DEFAULT_MODULE_OPEN_PREFERENCES)

    expect(screen.getByRole('heading', { name: 'Module open mode' })).toBeInTheDocument()
    // At least the single mode selector is present.
    expect(screen.getAllByRole('combobox').length).toBeGreaterThanOrEqual(1)
  })

  it('AC-014: in custom mode lists one selector per registered module', () => {
    renderField({ mode: 'custom', overrides: {} })

    // One selector for the global mode + one per registered module.
    expect(screen.getAllByRole('combobox')).toHaveLength(1 + MODULE_REGISTRY.length)

    for (const entry of MODULE_REGISTRY) {
      expect(screen.getByText(i18n.t(entry.labelKey))).toBeInTheDocument()
    }
  })

  it('AC-014: a global mode hides the per-module list', () => {
    renderField({ mode: 'modal', overrides: {} })

    // Only the global mode selector remains.
    expect(screen.getAllByRole('combobox')).toHaveLength(1)
  })

  it('AC-016: overrides are retained while a global mode is selected and reappear in custom', () => {
    // Held in a modal-mode value: the override is not shown but must not be lost.
    const withOverride: ModuleOpenPreferences = { mode: 'modal', overrides: { projects: 'page' } }
    const { rerender } = render(<ModuleOpenModeField value={withOverride} onChange={vi.fn()} />)
    expect(screen.getAllByRole('combobox')).toHaveLength(1)

    // Switching back to custom surfaces the preserved per-module choice.
    rerender(
      <ModuleOpenModeField
        value={{ mode: 'custom', overrides: { projects: 'page' } }}
        onChange={vi.fn()}
      />,
    )
    expect(screen.getByText('Single page')).toBeInTheDocument()
  })
})
