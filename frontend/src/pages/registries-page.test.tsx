import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import RegistriesPage from '@/pages/registries-page'

/**
 * Spec 0020 AC-019: the page gates the table behind `registries.viewAny` via
 * `<Can>`. `RegistriesTable` itself (TableView + CRUD sheets) is covered by
 * its own suite; here only the gate is under test, so the table is stubbed.
 */

vi.mock('@/features/registries/registries-table', () => ({
  RegistriesTable: () => <div data-testid="registries-table" />,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RegistriesPage', () => {
  it('shows the forbidden fallback and does not mount the table without registries.viewAny', () => {
    canMock.mockReturnValue(false)

    render(<RegistriesPage />)

    expect(screen.getByText("You don't have permission to view registries.")).toBeInTheDocument()
    expect(screen.queryByTestId('registries-table')).not.toBeInTheDocument()
  })

  it('mounts the table when registries.viewAny is granted', () => {
    canMock.mockReturnValue(true)

    render(<RegistriesPage />)

    expect(screen.getByTestId('registries-table')).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view registries."),
    ).not.toBeInTheDocument()
  })
})
