import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { DateTimeCellEditor } from '@/components/data-table/datetime-cell-editor'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0055 D-4: a `datetime` column edits through a real date/time control
 * whose value format IS the wire format, instead of the raw text field the
 * generic registry used to hand it.
 */

function renderEditor(props: Partial<CustomCellEditorProps<TableRow, string | null>> = {}) {
  const fullProps = {
    value: null,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    ...props,
  } as unknown as CustomCellEditorProps<TableRow, string | null>

  render((<DateTimeCellEditor {...fullProps} />) as ReactElement)

  return fullProps
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('DateTimeCellEditor', () => {
  it('renders a datetime control, focused, carrying the current value', () => {
    renderEditor({ value: '2026-07-22T09:30' })

    const input = screen.getByLabelText(i18n.t('table.dateTimeEditor.label'))

    expect(input).toHaveAttribute('type', 'datetime-local')
    expect(input).toHaveValue('2026-07-22T09:30')
    expect(input).toHaveFocus()
  })

  it('commits the picked value in the wire format, unparsed', () => {
    const props = renderEditor()

    fireEvent.change(screen.getByLabelText(i18n.t('table.dateTimeEditor.label')), {
      target: { value: '2026-08-01T14:00' },
    })

    expect(props.onValueChange).toHaveBeenCalledWith('2026-08-01T14:00')
  })

  it('commits null when the field is cleared', () => {
    const props = renderEditor({ value: '2026-07-22T09:30' })

    fireEvent.change(screen.getByLabelText(i18n.t('table.dateTimeEditor.label')), { target: { value: '' } })

    expect(props.onValueChange).toHaveBeenCalledWith(null)
  })

  it('closes the editor on Enter and on blur', () => {
    const props = renderEditor()
    const input = screen.getByLabelText(i18n.t('table.dateTimeEditor.label'))

    fireEvent.keyDown(input, { key: 'Enter' })
    fireEvent.blur(input)

    expect(props.stopEditing).toHaveBeenCalledTimes(2)
  })
})
