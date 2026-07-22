import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { RelationCellEditor, type RelationCellValue } from '@/components/data-table/relation-cell-editor'
import { fetchForSelect } from '@/features/for-select/api'
import type { PaginatedResponse, ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0054 AC-017 (+ D-9 single-click follow-up): a relation column's editor
 * mounts with its searchable, paginated dropdown ALREADY open (`defaultOpen`)
 * — the click that starts the cell edit is the only click the operator makes
 * — fed by the declared `/for-select` resource, and picking (or clearing) a
 * value commits it and closes the editor. Mocks only the HTTP boundary
 * (`fetchForSelect`) — `AsyncPaginatedSelect` itself and its `for-select`
 * hooks run for real, so this exercises the actual wiring.
 */

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: vi.fn(),
}))

const fetchForSelectMock = vi.mocked(fetchForSelect)

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, export_link: null, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1 } }
}

function renderEditor(props: Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const fullProps = {
    value: null,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    resource: 'users',
    ...props,
  } as unknown as CustomCellEditorProps<TableRow, RelationCellValue | null> & { resource: string }

  render(
    (<QueryClientProvider client={client}>
      <RelationCellEditor {...fullProps} />
    </QueryClientProvider>) as ReactElement,
  )

  return fullProps
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
})

describe('RelationCellEditor', () => {
  it('mounts with the searchable dropdown already open, fed by the declared /for-select resource — no second click (AC-017/D-9)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }, { id: 2, label: 'Luigi Bianchi' }]))
    renderEditor()

    expect(screen.getByRole('combobox', { name: i18n.t('table.relationEditor.trigger') })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(
      screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') }),
    ).toBeInTheDocument()
    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.any(Object)))
    expect(await screen.findByRole('option', { name: 'Mario Rossi' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Luigi Bianchi' })).toBeInTheDocument()
  })

  it('sends focus to the search field on mount (native `autoFocus`, no click needed)', () => {
    fetchForSelectMock.mockResolvedValue(page([]))
    renderEditor()

    expect(screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') })).toHaveFocus()
  })

  it('commits the picked item as {id, name} and stops editing, with a single click on the option (AC-017)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
    const props = renderEditor()

    fireEvent.click(await screen.findByRole('option', { name: 'Mario Rossi' }))

    expect(props.onValueChange).toHaveBeenCalledWith({ id: 1, name: 'Mario Rossi' })
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('commits null and stops editing when the current selection is cleared', () => {
    fetchForSelectMock.mockResolvedValue(page([]))
    const props = renderEditor({ value: { id: 5, name: 'Mario Rossi' } })

    fireEvent.click(screen.getByRole('button', { name: `Clear Mario Rossi` }))

    expect(props.onValueChange).toHaveBeenCalledWith(null)
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('shows the row\'s already-known {id, name} label immediately, even before the list fetch resolves', () => {
    // Never resolves: proves the trigger label comes from `selectedItem`
    // hydration, not from waiting on the list query.
    fetchForSelectMock.mockReturnValue(new Promise(() => {}))
    renderEditor({ value: { id: 5, name: 'Mario Rossi' } })

    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
  })

  it('never commits or stops editing on its own on Escape — the component wires no handler for it, leaving AG Grid\'s own cancel path untouched', () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
    const props = renderEditor()

    fireEvent.keyDown(
      screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') }),
      { key: 'Escape' },
    )

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).not.toHaveBeenCalled()
  })
})
