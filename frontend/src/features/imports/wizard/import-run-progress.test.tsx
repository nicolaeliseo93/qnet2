import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
// See the note in `import-step-summary.test.tsx`: the base bundle must
// register before this lane's deep-merge extension.
import '@/features/imports/wizard/i18n'
import { ImportRunProgress } from '@/features/imports/wizard/import-run-progress'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-024: the post-confirm view shows a busy indicator while
 * `processing`, the outcome (imported/error counts + notification note) once
 * `completed` — with the CSV error report link only when `has_error_report`
 * is set — and the failure notice once `failed`.
 */

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 3,
    resource: 'leads',
    status: 'processing',
    original_filename: 'leads.csv',
    total_rows: 10,
    valid_rows: 8,
    warning_rows: 1,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: null,
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [],
    global_fields: [],
    dedup_modes: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ImportRunProgress', () => {
  it('shows a busy indicator with a progressbar while processing', () => {
    render(<ImportRunProgress domain="leads" run={baseRun()} />)

    expect(screen.getByRole('status')).toHaveTextContent('Import in progress…')
    expect(screen.getByRole('progressbar')).toBeInTheDocument()
  })

  it('shows the outcome and the error report link once completed with errors', () => {
    render(
      <ImportRunProgress
        domain="leads"
        run={baseRun({ status: 'completed', imported_rows: 8, error_count: 2, has_error_report: true })}
      />,
    )

    expect(screen.getByText('8 leads imported, 2 errors.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Download error report' })).toBeInTheDocument()
  })

  it('hides the error report link once completed without errors', () => {
    render(
      <ImportRunProgress
        domain="leads"
        run={baseRun({ status: 'completed', imported_rows: 10, error_count: 0, has_error_report: false })}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Download error report' })).not.toBeInTheDocument()
  })

  it('shows the failure notice once failed', () => {
    render(<ImportRunProgress domain="leads" run={baseRun({ status: 'failed' })} />)

    expect(screen.getByRole('alert')).toHaveTextContent('The import failed. Please check the file and try again.')
  })
})
