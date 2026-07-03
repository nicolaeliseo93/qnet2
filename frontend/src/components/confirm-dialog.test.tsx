import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog-context'

/** Minimal harness: a button that opens the confirm and reports the outcome. */
function Harness({
  options,
  onResult,
}: {
  options?: ConfirmOptions
  onResult: (result: boolean) => void
}) {
  const confirm = useConfirm()
  return (
    <button type="button" onClick={async () => onResult(await confirm(options))}>
      open
    </button>
  )
}

function renderHarness(options: ConfirmOptions | undefined, onResult: (r: boolean) => void) {
  render(
    <ConfirmDialogProvider>
      <Harness options={options} onResult={onResult} />
    </ConfirmDialogProvider>,
  )
  fireEvent.click(screen.getByRole('button', { name: 'open' }))
}

describe('ConfirmDialogProvider / useConfirm', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders the provided title and description', async () => {
    renderHarness(
      { title: 'Delete item', description: 'This cannot be undone.' },
      vi.fn(),
    )

    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent('Delete item')
    expect(dialog).toHaveTextContent('This cannot be undone.')
  })

  it('resolves true when confirmed', async () => {
    const onResult = vi.fn()
    renderHarness({ confirmLabel: 'Yes' }, onResult)

    fireEvent.click(await screen.findByRole('button', { name: 'Yes' }))

    await waitFor(() => expect(onResult).toHaveBeenCalledWith(true))
  })

  it('resolves false when cancelled', async () => {
    const onResult = vi.fn()
    renderHarness(undefined, onResult)

    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(onResult).toHaveBeenCalledWith(false))
  })

  it('falls back to i18n defaults for the title and buttons', async () => {
    renderHarness(undefined, vi.fn())

    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent('Are you sure?')
    expect(screen.getByRole('button', { name: 'Confirm' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument()
  })
})
