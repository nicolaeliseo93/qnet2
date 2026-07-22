import { describe, expect, it } from 'vitest'
import { CELL_EDITOR_REGISTRY, resolveCellEditorSpec } from '@/components/data-table/cell-editor-registry'
import type { ColumnType, EnumBadge, TableColumn } from '@/features/table/types'

/** Minimal TableColumn stub; only the fields the registry reads matter. */
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

const ENUM_BADGES: EnumBadge[] = [
  { value: 'open', label: 'Open', color: 'blue', icon: null },
  { value: 'closed', label: 'Closed', color: 'slate', icon: null },
]

describe('resolveCellEditorSpec', () => {
  it('has an entry for every declared ColumnType (AC-023)', () => {
    const types: ColumnType[] = ['text', 'number', 'datetime', 'enum', 'tags', 'badge', 'boolean']
    for (const type of types) {
      expect(resolveCellEditorSpec(type)).toBeDefined()
    }
  })

  it('resolves to `undefined` for an unregistered type instead of throwing (AC-023)', () => {
    expect(() => resolveCellEditorSpec('future_type' as ColumnType)).not.toThrow()
    expect(resolveCellEditorSpec('future_type' as ColumnType)).toBeUndefined()
  })

  it('maps text/number/boolean/datetime to their plain built-in editors', () => {
    expect(resolveCellEditorSpec('text')?.cellEditor).toBe('agTextCellEditor')
    expect(resolveCellEditorSpec('number')?.cellEditor).toBe('agNumberCellEditor')
    expect(resolveCellEditorSpec('boolean')?.cellEditor).toBe('agCheckboxCellEditor')
    expect(resolveCellEditorSpec('datetime')?.cellEditor).toBe('agTextCellEditor')
  })

  it('maps enum/badge to a single-select rich editor sourced from column.options', () => {
    const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'], badges: ENUM_BADGES })
    const spec = resolveCellEditorSpec('badge')
    expect(spec?.cellEditor).toBe('agRichSelectCellEditor')
    const params = spec?.cellEditorParams?.(column)
    expect(params).toMatchObject({ values: ['open', 'closed'], multiSelect: false })
  })

  it('maps tags to a multi-select rich editor', () => {
    const column = stubColumn({ id: 'roles', type: 'tags', options: ['admin', 'editor'] })
    const spec = resolveCellEditorSpec('tags')
    const params = spec?.cellEditorParams?.(column)
    expect(params).toMatchObject({ values: ['admin', 'editor'], multiSelect: true })
  })

  it('falls back to an empty option list when the column has none', () => {
    const column = stubColumn({ id: 'status', type: 'enum' })
    const params = resolveCellEditorSpec('enum')?.cellEditorParams?.(column)
    expect(params).toMatchObject({ values: [] })
  })

  it('formats an enum/badge option through the column badges, raw value otherwise', () => {
    const column = stubColumn({ id: 'status', type: 'badge', badges: ENUM_BADGES })
    const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
      formatValue: (value: unknown) => string
    }
    expect(params.formatValue('open')).toBe('Open')
    expect(params.formatValue('unknown-value')).toBe('unknown-value')
  })

  it('every registry entry is reachable through the defensive lookup', () => {
    for (const type of Object.keys(CELL_EDITOR_REGISTRY) as ColumnType[]) {
      expect(resolveCellEditorSpec(type)).toBe(CELL_EDITOR_REGISTRY[type])
    }
  })
})
