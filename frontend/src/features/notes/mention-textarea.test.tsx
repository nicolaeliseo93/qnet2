import { useState } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { MentionTextarea } from '@/features/notes/mention-textarea'
import type { ForSelectItem } from '@/features/for-select/types'

/** Spec 0052 AC-072 — inline @mention autocomplete, keyboard-only, no popover/cmdk dependency. */

const useMentionableUsersMock = vi.fn()
vi.mock('@/features/notes/use-mentionable-users', () => ({
  useMentionableUsers: (args: unknown) => useMentionableUsersMock(args),
}))

const CANDIDATES: ForSelectItem[] = [
  { id: 12, label: 'Alice Verdi', avatar_url: null },
  { id: 7, label: 'Bob Neri', avatar_url: null },
  { id: 9, label: 'Carla Blu', avatar_url: null },
]

function pagesOf(items: ForSelectItem[]) {
  return { pages: [{ items }] }
}

/** Controlled test harness mirroring how `NoteComposer` drives the field. */
function Harness({ initialValue = '' }: { initialValue?: string }) {
  const [value, setValue] = useState(initialValue)
  const [mentions, setMentions] = useState<number[]>([])
  return (
    <>
      <label htmlFor="note-body">Note body</label>
      <MentionTextarea
        id="note-body"
        value={value}
        onChange={(nextValue, nextMentions) => {
          setValue(nextValue)
          setMentions(nextMentions)
        }}
        entityType="request-management"
        entityId={1}
      />
      <p>Mentions: [{mentions.join(',')}]</p>
    </>
  )
}

function field() {
  return screen.getByRole('combobox', { name: 'Note body' }) as HTMLTextAreaElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useMentionableUsersMock.mockReset()
  useMentionableUsersMock.mockReturnValue({ data: pagesOf(CANDIDATES), isPending: false })
})

describe('MentionTextarea', () => {
  it('opens the picker on "@" and exposes the correct combobox/listbox aria wiring', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })

    expect(field()).toHaveAttribute('aria-expanded', 'true')
    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getByRole('option', { name: /Alice Verdi/ })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: /Bob Neri/ })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: /Carla Blu/ })).toBeInTheDocument()
  })

  it('filters the query as more characters are typed, driving the lookup hook (debounced)', async () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@al' } })

    await waitFor(() =>
      expect(useMentionableUsersMock).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'al', enabled: true }),
      ),
    )
  })

  it('closes the picker once the caret leaves the trigger (space after the query)', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@al' } })
    expect(screen.getByRole('listbox')).toBeInTheDocument()

    fireEvent.change(field(), { target: { value: '@al ' } })
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('ArrowDown/ArrowUp move the highlighted option without moving the caret', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getByRole('option', { name: /Alice Verdi/ })).toHaveAttribute(
      'aria-selected',
      'true',
    )
    const caretBefore = field().selectionStart

    fireEvent.keyDown(field(), { key: 'ArrowDown' })
    expect(within(listbox).getByRole('option', { name: /Bob Neri/ })).toHaveAttribute(
      'aria-selected',
      'true',
    )
    expect(field().selectionStart).toBe(caretBefore)

    fireEvent.keyDown(field(), { key: 'ArrowUp' })
    expect(within(listbox).getByRole('option', { name: /Alice Verdi/ })).toHaveAttribute(
      'aria-selected',
      'true',
    )
    expect(field().selectionStart).toBe(caretBefore)
  })

  it('Escape closes the picker without inserting anything', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.keyDown(field(), { key: 'Escape' })

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    expect(field()).toHaveValue('@')
  })

  it('Enter selects the highlighted option, inserting the exact token format and the id into mentions', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.keyDown(field(), { key: 'Enter' })

    expect(field()).toHaveValue('@[Alice Verdi](user:12) ')
    expect(screen.getByText('Mentions: [12]')).toBeInTheDocument()
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('Tab inserts the highlighted option — the first one when the user has not moved', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.keyDown(field(), { key: 'Tab' })

    expect(field()).toHaveValue('@[Alice Verdi](user:12) ')
    expect(screen.getByText('Mentions: [12]')).toBeInTheDocument()
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('Tab after ArrowDown inserts the highlighted candidate, not the first one', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.keyDown(field(), { key: 'ArrowDown' })
    fireEvent.keyDown(field(), { key: 'Tab' })

    expect(field()).toHaveValue('@[Bob Neri](user:7) ')
  })

  it('Tab moves focus as usual when the picker is closed', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: 'plain text' } })
    fireEvent.keyDown(field(), { key: 'Tab' })

    expect(field()).toHaveValue('plain text')
  })

  it('CRITICAL: clicking an option inserts it — the mousedown fires before the field blurs', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.mouseDown(screen.getByRole('option', { name: /Carla Blu/ }))

    expect(field()).toHaveValue('@[Carla Blu](user:9) ')
    expect(screen.getByText('Mentions: [9]')).toBeInTheDocument()
  })

  it('selecting via ArrowDown then Enter inserts the second candidate', () => {
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@' } })
    fireEvent.keyDown(field(), { key: 'ArrowDown' })
    fireEvent.keyDown(field(), { key: 'Enter' })

    expect(field()).toHaveValue('@[Bob Neri](user:7) ')
    expect(screen.getByText('Mentions: [7]')).toBeInTheDocument()
  })

  it('CRITICAL: deleting a token by hand drops its id from mentions on the next change', () => {
    render(<Harness initialValue="Hello @[Alice Verdi](user:12) bye" />)

    // The user backspaces the token out of the body by hand.
    fireEvent.change(field(), { target: { value: 'Hello  bye' } })

    expect(screen.getByText('Mentions: []')).toBeInTheDocument()
  })

  it('a user mentioned twice in the body appears only once in mentions', () => {
    render(<Harness />)

    fireEvent.change(field(), {
      target: { value: '@[Alice Verdi](user:12) ping again @[Alice Verdi](user:12)' },
    })

    expect(screen.getByText('Mentions: [12]')).toBeInTheDocument()
  })

  it('shows the empty state when the lookup returns no candidates', () => {
    useMentionableUsersMock.mockReturnValue({ data: pagesOf([]), isPending: false })
    render(<Harness />)

    fireEvent.change(field(), { target: { value: '@zzz' } })

    expect(screen.getByText('No matching users')).toBeInTheDocument()
  })
})
