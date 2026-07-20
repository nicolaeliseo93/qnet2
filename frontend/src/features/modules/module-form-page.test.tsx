import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import ModuleFormPage from '@/features/modules/module-form-page'

/**
 * AC-005/AC-006 (spec 0045) — `ModuleFormPage` is the create form's only
 * params channel: it turns the current query string into `mode.params` so
 * `FormScreen` never needs its own `useSearchParams()`. A regression check
 * for the edit branch (untouched by spec 0045) closes the file.
 */

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'projects'
      ? {
          domain: 'projects',
          basePath: '/projects',
          defaultMode: 'page',
          labelKey: 'navigation.projects',
          DetailScreen: ({ id }: { id: number }) => <div>detail-{id}</div>,
          FormScreen: ({
            mode,
          }: {
            mode: { type: string; id?: number; params?: Record<string, string | number> }
          }) => (
            <div>
              <div>{`form-${mode.type}${mode.type === 'edit' ? `-${mode.id}` : ''}`}</div>
              {mode.type === 'create' && <div>{`params:${JSON.stringify(mode.params ?? null)}`}</div>}
            </div>
          ),
        }
      : undefined,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: () => null,
}))

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/projects/new" element={<ModuleFormPage domain="projects" />} />
        <Route path="/projects/:id/edit" element={<ModuleFormPage domain="projects" />} />
      </Routes>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ModuleFormPage', () => {
  it('AC-005: a query string on /new is turned into mode.params', () => {
    renderAt('/projects/new?lead_id=7')

    expect(screen.getByText('form-create')).toBeInTheDocument()
    expect(screen.getByText('params:{"lead_id":"7"}')).toBeInTheDocument()
  })

  it('AC-006: /new with no query string yields mode.params undefined', () => {
    renderAt('/projects/new')

    expect(screen.getByText('form-create')).toBeInTheDocument()
    expect(screen.getByText('params:null')).toBeInTheDocument()
  })

  it('regression: the edit branch is untouched by the params channel', () => {
    renderAt('/projects/5/edit')

    expect(screen.getByText('form-edit-5')).toBeInTheDocument()
  })
})
