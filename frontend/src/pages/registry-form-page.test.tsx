import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import RegistryFormPage from '@/pages/registry-form-page'
import type {
  RegistryDetail,
  RegistryDetailWithPermissions,
  RegistryFormMode,
} from '@/features/registries/types'

/**
 * Spec 0022 AC-A3/AC-A4 — the dedicated registry create/edit page: one page for
 * `/registries/new` and `/registries/:id/edit`, the mode derived from the `:id`
 * param; edit fetches the fresh detail first; a successful save navigates to
 * the detail page; cancel goes back to the detail (edit) or the list (create).
 * `RegistryForm` is stubbed: it is covered by its own suite, and what is under
 * test here is the page's mode/navigation wiring.
 */
const fetchRegistryMock = vi.fn<(id: number) => Promise<RegistryDetailWithPermissions>>()
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/registries/api', () => ({
  fetchRegistry: (id: number) => fetchRegistryMock(id),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const SAVED = { id: 12, name: 'Acme S.p.A.' } as RegistryDetail

vi.mock('@/features/registries/registry-form', () => ({
  RegistryForm: ({
    mode,
    onSuccess,
    onCancel,
  }: {
    mode: RegistryFormMode
    onSuccess: (registry: RegistryDetail) => void
    onCancel: () => void
  }) => (
    <div>
      <span>mode:{mode.type}</span>
      <button type="button" onClick={() => onSuccess(SAVED)}>
        save
      </button>
      <button type="button" onClick={onCancel}>
        cancel
      </button>
    </div>
  ),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <LocationProbe />
        <Routes>
          <Route path="/registries/new" element={<RegistryFormPage />} />
          <Route path="/registries/:id/edit" element={<RegistryFormPage />} />
          <Route path="*" element={null} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRegistryMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('RegistryFormPage — create (/registries/new)', () => {
  it('mounts the form in create mode without fetching a detail', async () => {
    renderAt('/registries/new')

    expect(await screen.findByText('mode:create')).toBeInTheDocument()
    expect(fetchRegistryMock).not.toHaveBeenCalled()
  })

  it('navigates to the new registry detail after a successful save', async () => {
    renderAt('/registries/new')

    fireEvent.click(await screen.findByRole('button', { name: 'save' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('returns to the list on cancel', async () => {
    renderAt('/registries/new')

    fireEvent.click(await screen.findByRole('button', { name: 'cancel' }))

    expect(screen.getByText('location:/registries')).toBeInTheDocument()
  })

  it('shows the forbidden fallback without registries.create', () => {
    canMock.mockImplementation((permission) => permission !== 'registries.create')

    renderAt('/registries/new')

    expect(screen.getByText("You don't have permission to view registries.")).toBeInTheDocument()
    expect(screen.queryByText('mode:create')).not.toBeInTheDocument()
  })
})

describe('RegistryFormPage — edit (/registries/:id/edit)', () => {
  it('fetches the fresh detail and mounts the form in edit mode', async () => {
    fetchRegistryMock.mockResolvedValue({ ...SAVED } as RegistryDetailWithPermissions)

    renderAt('/registries/12/edit')

    expect(await screen.findByText('mode:edit')).toBeInTheDocument()
    expect(fetchRegistryMock).toHaveBeenCalledWith(12)
  })

  it('navigates to the detail page after a successful save', async () => {
    fetchRegistryMock.mockResolvedValue({ ...SAVED } as RegistryDetailWithPermissions)

    renderAt('/registries/12/edit')

    fireEvent.click(await screen.findByRole('button', { name: 'save' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('returns to the detail page on cancel', async () => {
    fetchRegistryMock.mockResolvedValue({ ...SAVED } as RegistryDetailWithPermissions)

    renderAt('/registries/12/edit')

    fireEvent.click(await screen.findByRole('button', { name: 'cancel' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('shows the forbidden fallback without registries.update', () => {
    fetchRegistryMock.mockResolvedValue({ ...SAVED } as RegistryDetailWithPermissions)
    canMock.mockImplementation((permission) => permission !== 'registries.update')

    renderAt('/registries/12/edit')

    expect(screen.getByText("You don't have permission to view registries.")).toBeInTheDocument()
    expect(screen.queryByText('mode:edit')).not.toBeInTheDocument()
  })
})
