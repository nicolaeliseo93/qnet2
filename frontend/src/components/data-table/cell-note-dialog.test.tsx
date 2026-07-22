import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { CellNoteDialog } from '@/components/data-table/cell-note-dialog'

/**
 * Spec 0054 AC-020: the note dialog is accessible — Radix `Dialog`'s own
 * focus trap is left untouched (no `onOpenAutoFocus`/`onInteractOutside`
 * override), the textarea has an accessible label, a validation error is
 * announced (`role="alert"`, spec 0054 D-5 mirrors server enforcement without
 * replacing it — frontend.md §5), and Esc closes without submitting.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('CellNoteDialog', () => {
  it('labels the note field and exposes an accessible confirm/cancel pair', () => {
    render(<CellNoteDialog onConfirm={vi.fn()} onCancel={vi.fn()} />)

    expect(screen.getByLabelText(i18n.t('table.noteDialog.label'))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('table.noteDialog.confirm') })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('table.noteDialog.cancel') })).toBeInTheDocument()
  })

  it('announces a validation error on an empty submit, and does not confirm (AC-020)', async () => {
    const onConfirm = vi.fn()
    render(<CellNoteDialog onConfirm={onConfirm} onCancel={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.noteDialog.confirm') }))

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('table.noteDialog.required'))
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('confirms with the trimmed note once filled in', async () => {
    const onConfirm = vi.fn()
    render(<CellNoteDialog onConfirm={onConfirm} onCancel={vi.fn()} />)

    fireEvent.change(screen.getByLabelText(i18n.t('table.noteDialog.label')), {
      target: { value: '  Explaining the change  ' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.noteDialog.confirm') }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledWith('Explaining the change'))
  })

  it('cancels without confirming when the Cancel button is clicked', () => {
    const onConfirm = vi.fn()
    const onCancel = vi.fn()
    render(<CellNoteDialog onConfirm={onConfirm} onCancel={onCancel} />)

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.noteDialog.cancel') }))

    expect(onCancel).toHaveBeenCalledTimes(1)
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('cancels without confirming when closed via Esc (Radix Dialog default, focus trap untouched)', () => {
    const onConfirm = vi.fn()
    const onCancel = vi.fn()
    render(<CellNoteDialog onConfirm={onConfirm} onCancel={onCancel} />)

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(onCancel).toHaveBeenCalledTimes(1)
    expect(onConfirm).not.toHaveBeenCalled()
  })
})
