import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectDetailView } from '@/features/projects/project-detail'
import type { ProjectDetail } from '@/features/projects/types'

/** AC-044: the over-allocation warning shows only when `remaining_budget` is a negative amount. */

function project(overrides: Partial<ProjectDetail> = {}): ProjectDetail {
  return {
    id: 1,
    code: 'PRJ-0001',
    name: 'Acme rollout',
    description: null,
    registry_id: null,
    registry: null,
    project_status_id: 3,
    project_status: { id: 3, name: 'Active', color: 'blue' },
    source_id: null,
    source: null,
    business_function_id: null,
    business_function: null,
    state_id: null,
    state: null,
    product_category_id: null,
    product_category: null,
    partner_id: null,
    partner: null,
    start_date: null,
    end_date: null,
    total_budget: '1000.00',
    target_lead: null,
    allocated_budget: '1300.00',
    remaining_budget: '-300.00',
    campaigns_count: 2,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `projects` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
  i18n.addResourceBundle('en', 'translation', { projects: projectsEn }, true, true)
})

describe('ProjectDetailView — budget over-allocation warning (AC-044)', () => {
  it('shows the warning with the exceeding amount when remaining_budget is negative', () => {
    render(<ProjectDetailView project={project()} />)

    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('300')
  })

  it('does not show the warning when remaining_budget is positive', () => {
    render(<ProjectDetailView project={project({ allocated_budget: '400.00', remaining_budget: '600.00' })} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('does not show the warning when remaining_budget is exactly zero', () => {
    render(<ProjectDetailView project={project({ allocated_budget: '1000.00', remaining_budget: '0.00' })} />)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('does not show the warning when total_budget (and so remaining_budget) is unset', () => {
    render(
      <ProjectDetailView
        project={project({ total_budget: null, allocated_budget: '0.00', remaining_budget: null })}
      />,
    )

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
