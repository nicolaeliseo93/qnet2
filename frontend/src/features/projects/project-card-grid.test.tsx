import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ProjectCardGrid } from '@/features/projects/project-card-grid'
import type { ProjectCard } from '@/features/projects/types'

const useProjectCardsMock = vi.fn()
const fetchNextPage = vi.fn()
const refetch = vi.fn()

vi.mock('@/features/projects/use-project-cards', () => ({
  useProjectCards: (...args: unknown[]) => useProjectCardsMock(...args),
}))

function buildCard(overrides: Partial<ProjectCard> = {}): ProjectCard {
  return {
    id: 1,
    code: 'PRJ-0001',
    name: 'Alpha project',
    description: null,
    project_status: { id: 1, name: 'Active', color: 'green' },
    campaigns_count: 3,
    leads_count: 20,
    converted_leads_count: 5,
    conversion_rate: 25,
    geo_scope: 'country',
    geo_label: 'Italy',
    total_budget: '1000.00',
    allocated_budget: '400.00',
    remaining_budget: '600.00',
    start_date: null,
    end_date: null,
    can: { update: true, delete: true },
    ...overrides,
  }
}

interface BaseState {
  data: { pages: { items: ProjectCard[]; pagination: unknown }[] } | undefined
  isPending: boolean
  isError: boolean
  fetchNextPage: typeof fetchNextPage
  hasNextPage: boolean
  isFetchingNextPage: boolean
  refetch: typeof refetch
}

function baseState(overrides: Partial<BaseState> = {}): BaseState {
  return {
    data: undefined,
    isPending: false,
    isError: false,
    fetchNextPage,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch,
    ...overrides,
  }
}

function pagesOf(items: ProjectCard[]) {
  return { pages: [{ items, pagination: { total: items.length, offset: 0, limit: 12, total_pages: 1 } }] }
}

function renderGrid() {
  return render(
    <MemoryRouter>
      <ProjectCardGrid />
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useProjectCardsMock.mockReset()
  fetchNextPage.mockReset()
  refetch.mockReset()
})

describe('ProjectCardGrid (spec 0026 AC-007/009)', () => {
  it('renders cards from a mocked page', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([buildCard()]) }))

    renderGrid()

    expect(screen.getByText('PRJ-0001')).toBeInTheDocument()
    expect(screen.getByText('Alpha project')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('shows skeleton cards while the first page is loading', () => {
    useProjectCardsMock.mockReturnValue(baseState({ isPending: true }))

    const { container } = renderGrid()

    expect(container.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(0)
  })

  it('shows the empty state when there are no projects', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    expect(screen.getByText('No projects found.')).toBeInTheDocument()
  })

  it('shows the error state with a retry that refetches', () => {
    useProjectCardsMock.mockReturnValue(baseState({ isError: true }))

    renderGrid()

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('loads the next page when the bottom sentinel intersects (infinite scroll)', async () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard()]), hasNextPage: true }),
    )

    type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void
    let trigger: ObserverCallback | null = null
    const observe = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(cb: ObserverCallback) {
          trigger = cb
        }
        observe = observe
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return []
        }
      },
    )

    renderGrid()

    await waitFor(() => expect(observe).toHaveBeenCalled())
    const fire = trigger as ObserverCallback | null
    fire?.([{ isIntersecting: true }])
    expect(fetchNextPage).toHaveBeenCalled()

    vi.unstubAllGlobals()
  })

  it('hides the edit affordance when can.update is false', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ can: { update: false, delete: false } })]) }),
    )

    renderGrid()

    expect(screen.queryByRole('button', { name: 'Edit project' })).not.toBeInTheDocument()
  })

  it('shows the edit affordance when can.update is true', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([buildCard()]) }))

    renderGrid()

    expect(screen.getByRole('button', { name: 'Edit project' })).toBeInTheDocument()
  })

  it('shows the derived geo scope badge (spec 0027 AC-012)', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ geo_scope: 'city', geo_label: 'Milan' })]) }),
    )

    renderGrid()

    expect(screen.getByText('City')).toBeInTheDocument()
    expect(screen.getByText('Milan')).toBeInTheDocument()
  })

  it('omits the geo scope badge when the project has no geo at all', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ geo_scope: null, geo_label: null })]) }),
    )

    renderGrid()

    expect(screen.queryByText('National')).not.toBeInTheDocument()
  })
})
