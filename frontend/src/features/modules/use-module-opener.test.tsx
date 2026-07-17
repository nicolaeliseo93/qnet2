import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { OpenMode } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'

/**
 * AC-011/018/019: `useModuleOpener` routes view/edit/create to a modal Sheet
 * or to the dedicated-page deep-links depending on the resolved open mode.
 * The resolved mode and the registry entry are mocked so the test exercises
 * the routing decision alone, with trivial stub screens (no real fetch).
 */

let currentMode: OpenMode = 'modal'

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => currentMode,
}))

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'projects'
      ? {
          domain: 'projects',
          basePath: '/projects',
          defaultMode: 'modal',
          labelKey: 'navigation.projects',
          DetailScreen: ({ id }: { id: number }) => <div>detail-{id}</div>,
          FormScreen: ({ mode }: { mode: { type: string } }) => <div>form-{mode.type}</div>,
        }
      : undefined,
}))

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="location">{location.pathname}</div>
}

function Harness() {
  const { openCreate, openView, openEdit, sheet } = useModuleOpener('projects')
  return (
    <div>
      <button onClick={() => openView({ id: 5 } as TableRow)}>view</button>
      <button onClick={() => openEdit({ id: 7 } as TableRow)}>edit</button>
      <button onClick={openCreate}>create</button>
      {sheet}
      <LocationProbe />
    </div>
  )
}

function renderHarness() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/projects']}>
        <Harness />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useModuleOpener', () => {
  describe("page mode", () => {
    beforeEach(() => {
      currentMode = 'page'
    })

    it('AC-019: view/edit/create navigate to the deep-link routes and mount no Sheet', () => {
      renderHarness()

      // No Sheet is returned in page mode.
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'view' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/5')

      fireEvent.click(screen.getByRole('button', { name: 'edit' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/7/edit')

      fireEvent.click(screen.getByRole('button', { name: 'create' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/new')

      // Never mounted the modal content.
      expect(screen.queryByText(/^form-/)).not.toBeInTheDocument()
      expect(screen.queryByText(/^detail-/)).not.toBeInTheDocument()
    })
  })

  describe('modal mode', () => {
    beforeEach(() => {
      currentMode = 'modal'
    })

    it('AC-018: create opens the Sheet with the form and does not navigate', () => {
      renderHarness()

      // Sheet is closed initially: no content mounted, still on the list route.
      expect(screen.queryByText('form-create')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'create' }))

      expect(screen.getByText('form-create')).toBeInTheDocument()
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })

    it('AC-018: view opens the Sheet with the detail for the row and does not navigate', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'view' }))

      expect(screen.getByText('detail-5')).toBeInTheDocument()
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })
  })
})
