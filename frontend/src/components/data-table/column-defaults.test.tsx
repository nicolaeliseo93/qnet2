import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import appI18n from '@/i18n'
import {
  defaultValueFormatter,
  resolveCellRenderer,
  type CellRenderer,
} from '@/components/data-table/column-defaults'
import type { EnumBadge, TableColumn } from '@/features/table/types'

/** Minimal TableColumn stub; only the fields under test matter. */
function stubColumn(partial: Partial<TableColumn> & Pick<TableColumn, 'id' | 'type'>): TableColumn {
  return {
    label: 'label',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...partial,
  }
}

const translate = (key: string) => key

const ENUM_BADGES: EnumBadge[] = [
  { value: 'red', label: 'Red', color: 'red', icon: null },
  { value: 'blue', label: 'Blue', color: 'blue', icon: null },
]

function renderCell(renderer: CellRenderer | undefined, value: unknown) {
  if (!renderer) {
    throw new Error('expected a renderer')
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('resolveCellRenderer', () => {
  it('prefers an explicit per-id override, even for a custom enum column', () => {
    const column = stubColumn({ id: 'custom.color', type: 'enum', source: 'custom', badges: ENUM_BADGES })
    const override: CellRenderer = () => <span>overridden</span>
    const renderer = resolveCellRenderer(column, { 'custom.color': override })
    const { getByText } = renderCell(renderer, 'red')
    expect(getByText('overridden')).toBeInTheDocument()
  })

  it('falls back to the agnostic BadgeCell for a native `badge` column', () => {
    const column = stubColumn({ id: 'status', type: 'badge', badges: ENUM_BADGES })
    const renderer = resolveCellRenderer(column, undefined)
    const { getByText } = renderCell(renderer, 'blue')
    expect(getByText('Blue')).toBeInTheDocument()
  })

  it('falls back to the agnostic BadgeCell for a custom `enum` column, driven by column.badges/options', () => {
    const column = stubColumn({
      id: 'custom.color',
      type: 'enum',
      source: 'custom',
      badges: ENUM_BADGES,
      options: ['red', 'blue'],
    })
    const renderer = resolveCellRenderer(column, undefined)
    const { getByText } = renderCell(renderer, 'red')
    expect(getByText('Red')).toBeInTheDocument()
  })

  it('does NOT apply the badge fallback to a native `enum` column (no source: custom)', () => {
    // Native columns never carry type "enum" with badge metadata the way custom
    // fields do — the badge fallback is scoped to source:'custom' to avoid
    // changing any native column's behavior.
    const column = stubColumn({ id: 'kind', type: 'enum', badges: ENUM_BADGES })
    const renderer = resolveCellRenderer(column, undefined)
    expect(renderer).toBeUndefined()
  })

  it('has no renderer for a custom text/relation column (plain label string, default AG Grid cell)', () => {
    const column = stubColumn({ id: 'custom.notes', type: 'text', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })

  it('has no renderer for a custom number column (formatted via valueFormatter instead)', () => {
    const column = stubColumn({ id: 'custom.score', type: 'number', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })

  it('has no renderer for a custom boolean column (formatted via valueFormatter instead)', () => {
    const column = stubColumn({ id: 'custom.active', type: 'boolean', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })
})

describe('defaultValueFormatter', () => {
  it('joins a native `tags` array for display', () => {
    const column = stubColumn({ id: 'roles', type: 'tags' })
    expect(defaultValueFormatter(column, translate)?.(['admin', 'editor'])).toBe('admin, editor')
  })

  it('formats a custom boolean value to a localized yes/no', () => {
    const column = stubColumn({ id: 'custom.active', type: 'boolean', source: 'custom' })
    const format = defaultValueFormatter(column, translate)
    expect(format?.(true)).toBe('common.yes')
    expect(format?.(false)).toBe('common.no')
  })

  it('formats a custom number value with locale separators, blank when invalid', () => {
    const column = stubColumn({ id: 'custom.score', type: 'number', source: 'custom' })
    const format = defaultValueFormatter(column, translate)
    expect(format?.(1234)).toBe(new Intl.NumberFormat(appI18n.language).format(1234))
    // Backend decimal casts can serialize numbers as strings.
    expect(format?.('42.5')).toBe(new Intl.NumberFormat(appI18n.language).format(42.5))
    expect(format?.(null)).toBe('')
  })

  it('leaves a native number column untouched (no formatter — unaffected behavior)', () => {
    const column = stubColumn({ id: 'count', type: 'number' })
    expect(defaultValueFormatter(column, translate)).toBeUndefined()
  })

  it('leaves a native boolean column untouched (no formatter — unaffected behavior)', () => {
    const column = stubColumn({ id: 'is_active', type: 'boolean' })
    expect(defaultValueFormatter(column, translate)).toBeUndefined()
  })

  it('has no formatter for a custom text/relation or enum column', () => {
    expect(
      defaultValueFormatter(stubColumn({ id: 'custom.notes', type: 'text', source: 'custom' }), translate),
    ).toBeUndefined()
    expect(
      defaultValueFormatter(stubColumn({ id: 'custom.color', type: 'enum', source: 'custom' }), translate),
    ).toBeUndefined()
  })
})
