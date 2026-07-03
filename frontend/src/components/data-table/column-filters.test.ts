import { waitFor } from '@testing-library/react'
import type { SetFilterValuesFuncParams } from 'ag-grid-community'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  buildColumnFilter,
  createColumnValuesGetter,
  resolveFilter,
} from '@/components/data-table/column-filters'
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableColumnValues: vi.fn(),
}))

const fetchValuesMock = vi.mocked(fetchTableColumnValues)

/** Minimal TableColumn stub; only the fields `resolveFilter` reads matter. */
function stubColumn(
  partial: Partial<TableColumn> & Pick<TableColumn, 'id' | 'type'>,
): TableColumn {
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

describe('resolveFilter', () => {
  it('returns false for a non-filterable column', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'text', filterable: false })),
    ).toBe(false)
  })

  it.each(['text', 'number', 'date'] as const)(
    'returns agMultiColumnFilter for filterType "%s"',
    (filterType) => {
      expect(resolveFilter(stubColumn({ id: 'x', type: 'text', filterType }))).toBe(
        'agMultiColumnFilter',
      )
    },
  )

  it.each(['set', 'boolean'] as const)(
    'returns agSetColumnFilter for filterType "%s"',
    (filterType) => {
      expect(resolveFilter(stubColumn({ id: 'x', type: 'text', filterType }))).toBe(
        'agSetColumnFilter',
      )
    },
  )

  it.each(['enum', 'tags', 'badge', 'boolean'] as const)(
    'falls back to agSetColumnFilter for column type "%s" without an explicit filterType',
    (type) => {
      expect(resolveFilter(stubColumn({ id: 'x', type }))).toBe('agSetColumnFilter')
    },
  )

  it.each(['text', 'number', 'datetime'] as const)(
    'falls back to agMultiColumnFilter for column type "%s" without an explicit filterType',
    (type) => {
      expect(resolveFilter(stubColumn({ id: 'x', type }))).toBe('agMultiColumnFilter')
    },
  )

  it('still returns agMultiColumnFilter when hasFilterValues is explicitly true', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'text', hasFilterValues: true })),
    ).toBe('agMultiColumnFilter')
  })

  // 0005 (AC-018): computed/derived columns have no queryable value list, so a
  // Set Filter attached to them would call /values and fail or stay empty.
  it.each([
    ['text', 'agTextColumnFilter'],
    ['number', 'agNumberColumnFilter'],
    ['date', 'agDateColumnFilter'],
  ] as const)(
    'falls back to the plain %s condition filter when hasFilterValues is false',
    (filterType, expected) => {
      expect(
        resolveFilter(
          stubColumn({ id: 'x', type: 'text', filterType, hasFilterValues: false }),
        ),
      ).toBe(expected)
    },
  )

  it('ignores hasFilterValues for set/enum/boolean columns (always agSetColumnFilter)', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'enum', hasFilterValues: false })),
    ).toBe('agSetColumnFilter')
  })
})

// Passthrough translator: asserting on the raw key is enough to prove the
// sub-menu title was resolved through i18n rather than hardcoded.
const translate = (key: string) => key

describe('buildColumnFilter', () => {
  it('builds no filterParams (no Set tab, no values-callback) for a computed column', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'primary_address', type: 'text', hasFilterValues: false }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agTextColumnFilter')
    expect(filterParams).toBeUndefined()
  })

  // 0005 (AC-020/021): Excel-classic layout — the Set checklist is the primary,
  // inline view; the typed condition lives in a titled sub-menu, never a tab.
  it('inlines the Excel-mode Set checklist and tucks the condition into a titled sub-menu', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'email', type: 'text' }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agMultiColumnFilter')
    expect(filterParams).toMatchObject({
      filters: [
        { filter: 'agSetColumnFilter', filterParams: { excelMode: 'windows' } },
        { filter: 'agTextColumnFilter', display: 'subMenu', title: 'table.textFilters' },
      ],
    })
  })

  it.each([
    ['number', 'agNumberColumnFilter', 'table.numberFilters'],
    ['date', 'agDateColumnFilter', 'table.dateFilters'],
  ] as const)(
    'resolves the "%s" sub-menu title for the typed condition filter',
    (filterType, expectedFilter, expectedTitleKey) => {
      const { filterParams } = buildColumnFilter(
        'users',
        stubColumn({ id: 'x', type: 'text', filterType }),
        vi.fn(),
        translate,
      )

      expect(filterParams).toMatchObject({
        filters: [
          expect.anything(),
          { filter: expectedFilter, display: 'subMenu', title: expectedTitleKey },
        ],
      })
    },
  )

  it('gives a standalone Set Filter column (set/enum/boolean) the same Excel-mode checklist', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'user_type', type: 'enum' }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agSetColumnFilter')
    expect(filterParams).toMatchObject({ excelMode: 'windows' })
  })
})

/** Builds a minimal `SetFilterValuesFuncParams` stub carrying a fake filter model. */
function stubValuesParams(filterModel: Record<string, unknown>): SetFilterValuesFuncParams {
  return {
    api: { getFilterModel: () => filterModel },
    success: vi.fn(),
  } as unknown as SetFilterValuesFuncParams
}

describe('createColumnValuesGetter', () => {
  beforeEach(() => {
    fetchValuesMock.mockReset()
  })

  it('fetches values scoped to the OTHER columns, excluding the target column', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['a', 'b'], hasMore: false })
    const onTruncated = vi.fn()
    const params = stubValuesParams({
      email: { filterType: 'text', type: 'contains', filter: 'x' },
      roles: { filterType: 'set', values: ['admin'] },
    })

    createColumnValuesGetter('users', 'email', onTruncated)(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(fetchValuesMock).toHaveBeenCalledWith('users', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })
    expect(params.success).toHaveBeenCalledWith(['a', 'b'])
    expect(onTruncated).not.toHaveBeenCalled()
  })

  it('signals truncation when the backend reports hasMore', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['a'], hasMore: true })
    const onTruncated = vi.fn()
    const params = stubValuesParams({})

    createColumnValuesGetter('users', 'email', onTruncated)(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(onTruncated).toHaveBeenCalledOnce()
  })

  it('resolves to an empty list without crashing on a fetch failure', async () => {
    fetchValuesMock.mockRejectedValue(new Error('network error'))
    const params = stubValuesParams({})

    expect(() =>
      createColumnValuesGetter('users', 'email', vi.fn())(params),
    ).not.toThrow()
    await waitFor(() => expect(params.success).toHaveBeenCalledWith([]))
  })
})
