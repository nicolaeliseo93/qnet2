import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompanyDetailView } from '@/features/companies/company-detail'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'

/**
 * Spec 0034, AC-015: representative module for the activity log rollout
 * beyond Users. The "Activity log" DetailSection mounts only when the detail
 * envelope grants `permissions.actions.view_activity`.
 */

const fetchCompanyMock = vi.fn()
const activityLogSectionMock = vi.fn()

vi.mock('@/features/companies/api', () => ({
  fetchCompany: (...args: unknown[]) => fetchCompanyMock(...args),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function company(overrides: Partial<CompanyDetailWithPermissions> = {}): CompanyDetailWithPermissions {
  return {
    id: 1,
    denomination: 'Acme S.p.A.',
    vat_number: null,
    address: null,
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

function renderDetail(companyDetail: CompanyDetailWithPermissions) {
  fetchCompanyMock.mockResolvedValue(companyDetail)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <CompanyDetailView companyId={companyDetail.id} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCompanyMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('CompanyDetailView — activity log section (AC-015)', () => {
  it('mounts the section for the viewed company when view_activity is granted', async () => {
    renderDetail(company({ permissions: { ...company().permissions, actions: { view_activity: true } } }))

    await waitFor(() => expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument())
    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'companies', id: 1 })
  })

  it('hides the section when view_activity is not granted', async () => {
    renderDetail(company({ permissions: { ...company().permissions, actions: { view_activity: false } } }))

    await waitFor(() => expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument())
    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
