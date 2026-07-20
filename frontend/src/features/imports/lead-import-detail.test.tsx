import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadImportDetailView } from '@/features/imports/lead-import-detail'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

/**
 * Spec 0034 AC-013: the presentational detail view composes the shared
 * detail-panel kit around the run's own counters/metadata/errors/records.
 * `ReviewGrid` (AG Grid) and `ImportErrorReportLink` are framework/feature
 * pieces with their own suites — stubbed here to assert only the composition
 * (readOnly wiring, conditional sections) this component owns.
 */

const reviewGridPropsSpy = vi.fn()
vi.mock('@/features/imports/wizard/review-grid', () => ({
  ReviewGrid: (props: { domain: string; run: ImportRunDetail; readOnly?: boolean }) => {
    reviewGridPropsSpy(props)
    return <div role="region" aria-label="review-grid-stub" />
  },
}))

vi.mock('@/features/imports/import-error-report-link', () => ({
  ImportErrorReportLink: ({ domain, importRunId }: { domain: string; importRunId: number }) => (
    <div role="link" aria-label={`error-report-${domain}-${importRunId}`} />
  ),
}))

function run(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 9,
    resource: 'leads',
    status: 'completed',
    original_filename: 'leads-march.csv',
    total_rows: 10,
    valid_rows: 7,
    warning_rows: 1,
    error_rows: 2,
    duplicate_rows: 1,
    imported_rows: 6,
    modified_rows: 1,
    has_error_report: false,
    created_at: '2026-07-15T10:00:00Z',
    error_count: 2,
    detected_columns: null,
    column_mapping: null,
    global_config: null,
    dedup_strategy: 'create_new',
    suggested_mapping: null,
    fields: [{ id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' }],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

function summary(overrides: Partial<ImportRunSummaryReport> = {}): ImportRunSummaryReport {
  return {
    total_rows: 10,
    valid_rows: 7,
    warning_rows: 1,
    error_rows: 2,
    duplicate_rows: 1,
    modified_rows: 1,
    mapped_fields: [{ column: 'Email', field: 'email' }],
    extra_fields: [],
    global_config: {},
    dedup_strategy: 'create_new',
    warnings: [],
    conversion_readiness: {
      operational_site_set: true,
      campaign_derives_product_line: true,
      creatable_rows: 7,
      rows_without_operator: 0,
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('LeadImportDetailView', () => {
  it('renders the hero (filename, status) and the counters as tiles', () => {
    render(
      <LeadImportDetailView run={run()} summary={summary()} summaryIsLoading={false} summaryIsError={false} />,
    )

    expect(screen.getByRole('heading', { name: 'leads-march.csv' })).toBeInTheDocument()
    expect(screen.getByText('Completed')).toBeInTheDocument()
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByText('6')).toBeInTheDocument()
  })

  it('renders the mapped columns list once the summary is loaded', () => {
    render(
      <LeadImportDetailView run={run()} summary={summary()} summaryIsLoading={false} summaryIsError={false} />,
    )

    expect(screen.getAllByText('Email').length).toBeGreaterThan(0)
    expect(screen.getByText('→')).toBeInTheDocument()
  })

  it('shows a "no metadata" message when the summary is unavailable (run not yet reviewing)', () => {
    render(
      <LeadImportDetailView
        run={run()}
        summary={undefined}
        summaryIsLoading={false}
        summaryIsError={false}
      />,
    )

    expect(screen.getByText('No metadata available for this import.')).toBeInTheDocument()
  })

  it('shows a load error message when the summary fetch failed', () => {
    render(
      <LeadImportDetailView run={run()} summary={undefined} summaryIsLoading={false} summaryIsError={true} />,
    )

    expect(screen.getByText('Unable to load the import run. Please try again.')).toBeInTheDocument()
  })

  it('renders the error report link only when the run has one', () => {
    const { rerender } = render(
      <LeadImportDetailView
        run={run({ has_error_report: true })}
        summary={summary()}
        summaryIsLoading={false}
        summaryIsError={false}
      />,
    )
    expect(screen.getByRole('link', { name: 'error-report-leads-9' })).toBeInTheDocument()

    rerender(
      <LeadImportDetailView
        run={run({ has_error_report: false })}
        summary={summary()}
        summaryIsLoading={false}
        summaryIsError={false}
      />,
    )
    expect(screen.queryByRole('link', { name: 'error-report-leads-9' })).not.toBeInTheDocument()
  })

  it('mounts the review grid read-only, scoped to the leads domain', () => {
    render(
      <LeadImportDetailView run={run()} summary={summary()} summaryIsLoading={false} summaryIsError={false} />,
    )

    expect(screen.getByRole('region', { name: 'review-grid-stub' })).toBeInTheDocument()
    expect(reviewGridPropsSpy).toHaveBeenCalledWith(
      expect.objectContaining({ domain: 'leads', readOnly: true }),
    )
  })
})
