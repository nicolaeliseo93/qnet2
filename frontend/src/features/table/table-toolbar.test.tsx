import { createRef } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TableToolbar } from '@/features/table/table-toolbar'

// Assert against the English catalogue (the app default locale is Italian).
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Baseline props; each test overrides only what it exercises. */
function baseProps() {
  return {
    searchEnabled: true,
    searchPlaceholder: 'Search name/email…',
    searchInputRef: createRef<HTMLInputElement>(),
    searchValue: '',
    onSearchChange: vi.fn(),
    searchShortcut: '⌘K',
    rowCount: 50,
    filtersActive: false,
    onResetFilters: vi.fn(),
    resettingFilters: false,
    layoutCustomized: false,
    onResetLayout: vi.fn(),
    resettingLayout: false,
    fullscreen: false,
    onToggleFullscreen: vi.fn(),
  }
}

function renderToolbar(overrides: Partial<ReturnType<typeof baseProps>> = {}) {
  const props = { ...baseProps(), ...overrides }
  render(
    <I18nextProvider i18n={i18n}>
      <TooltipProvider>
        <TableToolbar {...props} />
      </TooltipProvider>
    </I18nextProvider>,
  )
  return props
}

describe('TableToolbar', () => {
  it('shows the search field with its placeholder and the live row count', () => {
    renderToolbar()

    expect(screen.getByPlaceholderText('Search name/email…')).toBeInTheDocument()
    expect(screen.getByText('50 rows')).toBeInTheDocument()
  })

  it('hides the search field when the domain has no searchable columns', () => {
    renderToolbar({ searchEnabled: false })

    expect(screen.queryByPlaceholderText('Search name/email…')).not.toBeInTheDocument()
  })

  it('emits every keystroke through onSearchChange', () => {
    const { onSearchChange } = renderToolbar()

    fireEvent.change(screen.getByPlaceholderText('Search name/email…'), {
      target: { value: 'ann' },
    })

    expect(onSearchChange).toHaveBeenCalledWith('ann')
  })

  it('offers the reset-filters control only while filters are active', () => {
    const { onResetFilters } = renderToolbar({ filtersActive: true })

    fireEvent.click(screen.getByRole('button', { name: 'Reset filters' }))
    expect(onResetFilters).toHaveBeenCalledTimes(1)
  })

  it('does not render the reset-filters control when no filter is active', () => {
    renderToolbar({ filtersActive: false })

    expect(screen.queryByRole('button', { name: 'Reset filters' })).not.toBeInTheDocument()
  })

  it('toggles fullscreen and swaps its label', () => {
    const { onToggleFullscreen } = renderToolbar({ fullscreen: true })

    fireEvent.click(screen.getByRole('button', { name: 'Exit fullscreen' }))
    expect(onToggleFullscreen).toHaveBeenCalledTimes(1)
  })
})
