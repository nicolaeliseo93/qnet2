import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ContactForm } from '@/features/personal-data/contact-form'
import type { EnumOption } from '@/features/config/types'
import type { ContactDraft } from '@/features/personal-data/types'

const contactTypes: EnumOption[] = [
  {
    value: 'email',
    label: 'Email',
    color: null,
    icon: null,
    is_default: true,
    hidden_on_form: false,
  },
  {
    value: 'phone',
    label: 'Phone',
    color: null,
    icon: null,
    is_default: false,
    hidden_on_form: false,
  },
]

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums: { contact_type: contactTypes } } }),
  useEnumOptions: (key: string) =>
    key === 'contact_type' ? contactTypes : [],
}))

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'contact-1',
    id: 7,
    type: 'email',
    label: 'Work',
    value: 'ada@example.com',
    is_primary: true,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ContactForm (controlled)', () => {
  it('validates the value per type and blocks an invalid email', async () => {
    const onSubmit = vi.fn()
    render(<ContactForm onSubmit={onSubmit} onCancel={() => {}} />)

    fireEvent.change(screen.getByLabelText(/^Value/), {
      target: { value: 'not-an-email' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(
      await screen.findByText('Enter a valid email address.'),
    ).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('returns the draft fields (no _key) and forwards is_primary', async () => {
    const onSubmit = vi.fn()
    render(<ContactForm onSubmit={onSubmit} onCancel={() => {}} />)

    fireEvent.change(screen.getByLabelText(/^Value/), {
      target: { value: 'ada@example.com' },
    })
    fireEvent.click(
      screen.getByRole('checkbox', { name: 'Primary contact' }),
    )
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    const fields = onSubmit.mock.calls[0][0]
    expect(fields).not.toHaveProperty('_key')
    expect(fields.value).toBe('ada@example.com')
    expect(fields.type).toBe('email')
    expect(fields.is_primary).toBe(true)
  })

  it('preserves the existing id when editing', async () => {
    const onSubmit = vi.fn()
    render(
      <ContactForm
        contact={contact({ id: 42 })}
        onSubmit={onSubmit}
        onCancel={() => {}}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0].id).toBe(42)
  })

  it('formats the value for its channel when the field loses focus', async () => {
    const onSubmit = vi.fn()
    render(
      <ContactForm
        contact={contact({ type: 'phone', value: '' })}
        onSubmit={onSubmit}
        onCancel={() => {}}
      />,
    )

    const input = screen.getByLabelText(/^Value/)
    fireEvent.change(input, { target: { value: '333 12 34 567' } })
    fireEvent.blur(input)

    await waitFor(() => expect(input).toHaveValue('3331234567'))

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0].value).toBe('3331234567')
  })

  it('cancels without submitting', () => {
    const onCancel = vi.fn()
    const onSubmit = vi.fn()
    render(<ContactForm onSubmit={onSubmit} onCancel={onCancel} />)

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalledTimes(1)
    expect(onSubmit).not.toHaveBeenCalled()
  })
})
