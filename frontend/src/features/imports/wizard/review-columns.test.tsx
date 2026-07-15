import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewMessagesCell,
  ReviewStatusCell,
  buildReviewColumnDefs,
  reviewValueKeyOf,
} from '@/features/imports/wizard/review-columns'
import type { ImportRunDetail, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-023: the review grid's columns are derived client-side from
 * the run's frozen mapping — no `columns` endpoint exists for the review
 * domain (unlike the generic SSRM tables).
 */

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 3,
    valid_rows: 2,
    warning_rows: 1,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Email', name: 'Email', index: 0, duplicate: false },
      { key: 'Phone', name: 'Phone', index: 1, duplicate: false },
      { key: 'Notes column', name: 'Notes column', index: 2, duplicate: false },
    ],
    column_mapping: { Email: 'email', Phone: '__ignore__', 'Notes column': '__extra__' },
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [
      { id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' },
      { id: 'phone', label: 'Phone', required: false, group: 'contact', type: 'string' },
    ],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'warning',
    is_edited: false,
    duplicate_of_id: null,
    values: { email: 'mario@example.com' },
    messages: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildReviewColumnDefs', () => {
  it('builds row_number/status as read-only, one editable column per mapped field, one per extra column, and a trailing messages column', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    const colIds = colDefs.map((col) => col.colId)

    expect(colIds).toEqual(['row_number', 'status', 'field:email', 'extra:Notes column', 'messages'])
    expect(colDefs.find((col) => col.colId === 'row_number')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'status')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'field:email')?.editable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'extra:Notes column')?.editable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'messages')?.editable).toBe(false)
  })

  it('leaves the ignored column (Phone) out of the grid entirely', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    expect(colDefs.some((col) => col.colId === 'field:phone')).toBe(false)
  })

  it('only allows sorting on the backend allow-list (row_number, status, mapped field ids)', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    expect(colDefs.find((col) => col.colId === 'field:email')?.sortable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'extra:Notes column')?.sortable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'messages')?.sortable).toBe(false)
  })

  it("the mapped field column's valueGetter/valueSetter read and write `values` by field id", () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    const emailColumn = colDefs.find((col) => col.colId === 'field:email')!
    const valueGetter = emailColumn.valueGetter as (params: unknown) => unknown
    const valueSetter = emailColumn.valueSetter as (params: unknown) => boolean
    const data = rowItem({ values: { email: 'old@example.com' } })

    expect(valueGetter({ data })).toBe('old@example.com')

    const setterResult = valueSetter({ data, oldValue: 'old@example.com', newValue: 'new@example.com' })
    expect(setterResult).toBe(true)
    expect(data.values.email).toBe('new@example.com')
  })
})

describe('reviewValueKeyOf', () => {
  it('resolves a mapped-field colId to its field id', () => {
    expect(reviewValueKeyOf('field:email')).toBe('email')
  })

  it('resolves an extra-column colId to its original column name', () => {
    expect(reviewValueKeyOf('extra:Notes column')).toBe('Notes column')
  })

  it('returns null for a service column', () => {
    expect(reviewValueKeyOf('status')).toBeNull()
    expect(reviewValueKeyOf('row_number')).toBeNull()
    expect(reviewValueKeyOf('messages')).toBeNull()
  })
})

describe('ReviewStatusCell', () => {
  it('renders the translated status badge', () => {
    render(<ReviewStatusCell {...({ data: rowItem({ status: 'error' }) } as ICellRendererParams)} />)
    expect(screen.getByText('Error')).toBeInTheDocument()
  })

  it('marks an edited row with a titled indicator', () => {
    const { container } = render(
      <ReviewStatusCell {...({ data: rowItem({ status: 'valid', is_edited: true }) } as ICellRendererParams)} />,
    )
    expect(container.querySelector('[title="Edited"]')).not.toBeNull()
  })
})

describe('ReviewMessagesCell', () => {
  it('renders an em dash when there are no messages', () => {
    render(<ReviewMessagesCell {...({ data: rowItem({ messages: [] }) } as ICellRendererParams)} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('joins the row messages', () => {
    render(
      <ReviewMessagesCell
        {...({ data: rowItem({ messages: ['Email format guessed', 'Low-confidence name split'] }) } as ICellRendererParams)}
      />,
    )
    expect(screen.getByText('Email format guessed · Low-confidence name split')).toBeInTheDocument()
  })
})
