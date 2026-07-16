import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import '@/features/imports/wizard/i18n'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-022: the mapping step pre-populates each column's select from
 * the auto-mapping suggestion, flags a required field left unmapped, and
 * persists (via `onSubmit`, wired to `PUT .../configure` upstream) the
 * mapping + dedup strategy once valid.
 *
 * `SavedTemplatesMenu` (spec 0035) needs its own `QueryClientProvider`/auth
 * context — irrelevant to this file's AC-009/010/011 coverage (which is
 * about the banner/toggle, not the management popover, covered separately
 * in `mapping-template-controls.test.tsx`) — so it is stubbed out here.
 */
vi.mock('@/features/imports/wizard/mapping-template-controls', async () => {
  const actual = await vi.importActual<typeof import('@/features/imports/wizard/mapping-template-controls')>(
    '@/features/imports/wizard/mapping-template-controls',
  )
  return { ...actual, SavedTemplatesMenu: () => null }
})

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'configuring',
    original_filename: 'leads.csv',
    total_rows: 10,
    valid_rows: 0,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Full name', name: 'Full name', index: 0, duplicate: false },
      { key: 'Email', name: 'Email', index: 1, duplicate: false },
    ],
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: { 'Full name': 'full_name' },
    fields: [
      { id: 'full_name', label: 'Full name', required: true, group: 'contact', type: 'string' },
      { id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' },
    ],
    global_fields: [],
    dedup_modes: ['create_new', 'update_existing'],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ImportStepMapping', () => {
  it('pre-populates the mapping from the suggested/persisted mapping', () => {
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{ 'Full name': 'full_name' }}
        initialDedupStrategy={null}
        onBack={vi.fn()}
        onSubmit={vi.fn()}
        isSubmitting={false}
        submitError={null}
      />,
    )

    expect(screen.getAllByRole('combobox', { name: 'Target field' })[0]).toHaveTextContent('Full name *')
  })

  it('flags the required field left unmapped and blocks submit', async () => {
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{}}
        initialDedupStrategy="create_new"
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Required fields not mapped: Full name, Email')
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('submits the mapping and dedup strategy once every required field is covered', async () => {
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{ 'Full name': 'full_name', Email: 'email' }}
        initialDedupStrategy="update_existing"
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(
        { 'Full name': 'full_name', Email: 'email' },
        'update_existing',
      ),
    )
  })

  it('submits with an un-auto-mapped column defaulted to ignore (regression: dead submit button)', async () => {
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun({
          detected_columns: [
            { key: 'Full name', name: 'Full name', index: 0, duplicate: false },
            { key: 'Email', name: 'Email', index: 1, duplicate: false },
            { key: 'Internal id', name: 'Internal id', index: 2, duplicate: false },
          ],
        })}
        initialMapping={{ 'Full name': 'full_name', Email: 'email' }}
        initialDedupStrategy="create_new"
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    // The extra "Internal id" column is not covered by the auto-mapping; before
    // the fix its RHF value stayed `undefined`, failing z.record silently and
    // dead-locking the submit button. It must now default to IGNORE and submit.
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(
        { 'Full name': 'full_name', Email: 'email', 'Internal id': IGNORE_TARGET },
        'create_new',
      ),
    )
  })

  it('flags a duplicate-named column and a mapping conflict', () => {
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{}}
        initialDedupStrategy="create_new"
        onBack={vi.fn()}
        onSubmit={vi.fn()}
        isSubmitting={false}
        submitError={null}
      />,
    )

    const rows = screen.getAllByRole('combobox', { name: 'Target field' })
    fireEvent.click(rows[0])
    fireEvent.click(screen.getByRole('option', { name: /^Full name/ }))
    fireEvent.click(rows[1])
    fireEvent.click(screen.getByRole('option', { name: /^Full name/ }))

    expect(screen.getAllByText('Mapped more than once')).toHaveLength(2)
  })

  it('submits column keys verbatim even when a key contains a dot/comma/question mark (regression: RHF path mangling)', async () => {
    // Real-world survey headers end in a period and contain commas/question
    // marks; used as an RHF field-name path, a `.` splits/strips the key and
    // the backend rejects the mismatched column_mapping. Keying the form by
    // column index must preserve the exact key on submit.
    const trickyKey = 'hai_un_contratto_di_lavoro?_i_corsi_sono_rivolti_a_disoccupati_e_percettori_di_adi,_naspi_o_sfl.'
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun({
          detected_columns: [
            { key: 'Email', name: 'Email', index: 0, duplicate: false },
            { key: trickyKey, name: trickyKey, index: 1, duplicate: false },
          ],
          fields: [{ id: 'email', label: 'Email', required: false, group: 'contact', type: 'string' }],
        })}
        initialMapping={{ Email: 'email' }}
        initialDedupStrategy="create_new"
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(
        { Email: 'email', [trickyKey]: IGNORE_TARGET },
        'create_new',
      ),
    )
  })

  it('calls onBack from the back action', () => {
    const onBack = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{}}
        initialDedupStrategy="create_new"
        onBack={onBack}
        onSubmit={vi.fn()}
        isSubmitting={false}
        submitError={null}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Back' }))
    expect(onBack).toHaveBeenCalledTimes(1)
  })

  it('applies a matching template on click, leaving the mapping/strategy editable (AC-009)', async () => {
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun({
          // A third, non-required column absent from the template's mapping
          // isolates the "stays editable" assertion from the required-field
          // gate below (flipping Email/Full name off would block submit).
          detected_columns: [
            { key: 'Full name', name: 'Full name', index: 0, duplicate: false },
            { key: 'Email', name: 'Email', index: 1, duplicate: false },
            { key: 'Notes', name: 'Notes', index: 2, duplicate: false },
          ],
          matching_template: {
            id: 5,
            name: 'My template',
            column_mapping: { 'Full name': 'full_name', Email: 'email' },
            dedup_strategy: 'update_existing',
          },
        })}
        initialMapping={{}}
        initialDedupStrategy={null}
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    expect(screen.getByRole('status')).toHaveTextContent('My template')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    const rows = screen.getAllByRole('combobox', { name: 'Target field' })
    expect(rows[0]).toHaveTextContent('Full name *')
    expect(rows[1]).toHaveTextContent('Email *')

    // The applied mapping stays editable: change the untouched third column.
    fireEvent.click(rows[2])
    fireEvent.click(screen.getByRole('option', { name: 'Extra field' }))

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(
        { 'Full name': 'full_name', Email: 'email', Notes: EXTRA_TARGET },
        'update_existing',
      ),
    )
  })

  it('renders no matching-template banner when the run has none (AC-010)', () => {
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{}}
        initialDedupStrategy="create_new"
        onBack={vi.fn()}
        onSubmit={vi.fn()}
        isSubmitting={false}
        submitError={null}
      />,
    )

    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('passes the save-as-template request to onSubmit once checked with a name (AC-011)', async () => {
    const onSubmit = vi.fn()
    render(
      <ImportStepMapping
        run={baseRun()}
        initialMapping={{ 'Full name': 'full_name', Email: 'email' }}
        initialDedupStrategy="update_existing"
        onBack={vi.fn()}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />,
    )

    fireEvent.click(screen.getByRole('checkbox', { name: 'Save this mapping as a reusable template' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Template name' }), {
      target: { value: 'Monthly leads' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(
        { 'Full name': 'full_name', Email: 'email' },
        'update_existing',
        { name: 'Monthly leads' },
      ),
    )
  })
})
