import { describe, expect, it } from 'vitest'
import {
  CELL_EDITOR_REGISTRY,
  resolveCellEditorSpec,
  type CellEditorKind,
} from '@/components/data-table/cell-editor-registry'
import { RelationCellEditor } from '@/components/data-table/relation-cell-editor'
import type { ColumnType, EnumBadge, TableColumn, TableRow } from '@/features/table/types'

/** A `RichCellEditorValuesCallback` param stub: only `data` (the editing row) is read. */
function valuesParams(data: TableRow | undefined) {
  return { data } as { data?: TableRow }
}

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
    const params = spec?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
      multiSelect: boolean
    }
    expect(params.values(valuesParams(undefined))).toEqual(['open', 'closed'])
    expect(params.multiSelect).toBe(false)
  })

  it('maps tags to a multi-select rich editor', () => {
    const column = stubColumn({ id: 'roles', type: 'tags', options: ['admin', 'editor'] })
    const spec = resolveCellEditorSpec('tags')
    const params = spec?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
      multiSelect: boolean
    }
    expect(params.values(valuesParams(undefined))).toEqual(['admin', 'editor'])
    expect(params.multiSelect).toBe(true)
  })

  it('falls back to an empty option list when the column has none', () => {
    const column = stubColumn({ id: 'status', type: 'enum' })
    const params = resolveCellEditorSpec('enum')?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
    }
    expect(params.values(valuesParams(undefined))).toEqual([])
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
    for (const kind of Object.keys(CELL_EDITOR_REGISTRY) as CellEditorKind[]) {
      expect(resolveCellEditorSpec(kind)).toBe(CELL_EDITOR_REGISTRY[kind])
    }
  })

  describe('row-scoped options (spec 0054 follow-up, AC-026/027)', () => {
    it("filters the catalog down to the row's <columnId>_options when present", () => {
      const column = stubColumn({ id: 'workflow_status', type: 'badge', options: ['1', '2', '3'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], workflow_status_options: [1, 3] }

      expect(params.values(valuesParams(row))).toEqual(['1', '3'])
    })

    it('offers the full catalog when the row carries no <columnId>_options (no regression)', () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [] }

      expect(params.values(valuesParams(row))).toEqual(['open', 'closed'])
    })

    it('offers the full catalog when there is no row at all (defensive)', () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }

      expect(params.values(valuesParams(undefined))).toEqual(['open', 'closed'])
    })

    it("is driven by the row's shape, never by column id: any column adopting the convention gets filtered", () => {
      const column = stubColumn({ id: 'anything', type: 'enum', options: ['a', 'b', 'c'] })
      const params = resolveCellEditorSpec('enum')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], anything_options: ['b'] }

      expect(params.values(valuesParams(row))).toEqual(['b'])
    })

    it("ignores a malformed <columnId>_options that isn't an array (defensive, falls back to the full catalog)", () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], status_options: 'not-an-array' }

      expect(params.values(valuesParams(row))).toEqual(['open', 'closed'])
    })
  })

  describe('relation kind (spec 0054 D-7)', () => {
    it('maps to the custom RelationCellEditor component, rendered as a popup', () => {
      const spec = resolveCellEditorSpec('relation')
      expect(spec?.cellEditor).toBe(RelationCellEditor)
      expect(spec?.cellEditorPopup).toBe(true)
    })

    it('forwards the column\'s declared for-select resource as cellEditorParams (D-1)', () => {
      const column = stubColumn({ id: 'operator', type: 'text', relation: { resource: 'users' } })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({ resource: 'users' })
    })

    it('falls back to an empty resource string when the column carries none (defensive)', () => {
      const column = stubColumn({ id: 'operator', type: 'text' })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({ resource: '' })
    })
  })
})
