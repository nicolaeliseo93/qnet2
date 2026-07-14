import { describe, expect, it, vi } from 'vitest'
import { lazy } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { QuickCreateButton } from '@/features/quick-create/quick-create-button'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'

/**
 * Spec 0028 — the registry lookup itself is covered by
 * `quick-create-registry.test.ts`; this suite isolates the button/dialog
 * wiring by mocking `resolveQuickCreate`, so it never has to boot a real
 * module form (RHF, meta fetches, ...) to assert its own behaviour.
 */

const resolveQuickCreateMock = vi.fn<(resource: string) => QuickCreateEntry | null>()
vi.mock('@/features/quick-create/quick-create-registry', () => ({
  resolveQuickCreate: (resource: string) => resolveQuickCreateMock(resource),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

function fakeEntry(onSuccessRef: { id: number; name: string } = { id: 42, name: 'Nuova fonte' }): QuickCreateEntry {
  return {
    titleKey: 'sources.form.createTitle',
    descriptionKey: 'sources.form.createSubtitle',
    permission: 'sources.create',
    form: lazy(async () => ({
      default: ({ onSuccess }: QuickCreateFormProps) => (
        <button type="button" onClick={() => onSuccess(onSuccessRef)}>
          fake-submit
        </button>
      ),
    })),
  }
}

describe('QuickCreateButton', () => {
  it('renders the "+" as an accessible type="button" when the resource is registered and permitted (AC-001)', async () => {
    resolveQuickCreateMock.mockReturnValue(fakeEntry())
    canMock.mockReturnValue(true)

    render(<QuickCreateButton resource="sources" onCreated={vi.fn()} />)

    const button = await screen.findByRole('button', { name: i18n.t('sources.form.createTitle') })
    expect(button).toHaveAttribute('type', 'button')
  })

  it('renders nothing when the actor lacks the {domain}.create permission (AC-002)', () => {
    resolveQuickCreateMock.mockReturnValue(fakeEntry())
    canMock.mockReturnValue(false)

    render(<QuickCreateButton resource="sources" onCreated={vi.fn()} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('renders nothing and does not throw for a resource outside the registry (AC-011)', () => {
    resolveQuickCreateMock.mockReturnValue(null)
    canMock.mockReturnValue(true)

    expect(() => render(<QuickCreateButton resource="states" onCreated={vi.fn()} />)).not.toThrow()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('opens the dialog with the module form, and on success closes it and forwards the ref', async () => {
    resolveQuickCreateMock.mockReturnValue(fakeEntry({ id: 42, name: 'Nuova fonte' }))
    canMock.mockReturnValue(true)
    const onCreated = vi.fn()

    render(<QuickCreateButton resource="sources" onCreated={onCreated} />)

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sources.form.createTitle') }))
    const submit = await screen.findByRole('button', { name: 'fake-submit' })
    fireEvent.click(submit)

    await waitFor(() => expect(onCreated).toHaveBeenCalledWith({ id: 42, name: 'Nuova fonte' }))
    await waitFor(() => expect(screen.queryByRole('button', { name: 'fake-submit' })).not.toBeInTheDocument())
  })

  it('does not submit the surrounding form when the dialog form is submitted (AC-008)', async () => {
    // React bubbles the synthetic `submit` along the React tree, so without a
    // boundary at the portal the parent form submits too — the bug this covers.
    resolveQuickCreateMock.mockReturnValue({
      titleKey: 'sources.form.createTitle',
      descriptionKey: 'sources.form.createSubtitle',
      permission: 'sources.create',
      form: lazy(async () => ({
        default: ({ onSuccess }: QuickCreateFormProps) => (
          <form
            onSubmit={(event) => {
              event.preventDefault()
              onSuccess({ id: 7, name: 'Nuova fonte' })
            }}
          >
            <button type="submit">inner-submit</button>
          </form>
        ),
      })),
    })
    canMock.mockReturnValue(true)
    const parentSubmit = vi.fn((event: React.FormEvent) => event.preventDefault())
    const onCreated = vi.fn()

    render(
      <form onSubmit={parentSubmit}>
        <QuickCreateButton resource="sources" onCreated={onCreated} />
      </form>,
    )

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sources.form.createTitle') }))
    fireEvent.click(await screen.findByRole('button', { name: 'inner-submit' }))

    await waitFor(() => expect(onCreated).toHaveBeenCalledWith({ id: 7, name: 'Nuova fonte' }))
    expect(parentSubmit).not.toHaveBeenCalled()
  })
})
