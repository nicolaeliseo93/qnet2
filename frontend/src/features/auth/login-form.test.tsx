import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LoginForm } from '@/features/auth/login-form'

const loginMock = vi.fn()

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ login: (...args: unknown[]) => loginMock(...args) }),
}))

describe('LoginForm', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    loginMock.mockReset()
    loginMock.mockResolvedValue(undefined)
  })

  it('does not show required markers for email and password', () => {
    render(<LoginForm onSuccess={vi.fn()} />)

    expect(screen.queryByText('*')).not.toBeInTheDocument()
  })

  it('submits empty credentials without showing required-field errors', async () => {
    render(<LoginForm onSuccess={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    await waitFor(() =>
      expect(loginMock).toHaveBeenCalledWith({
        email: '',
        password: '',
      }),
    )
    expect(screen.queryByText('Email is required.')).not.toBeInTheDocument()
    expect(screen.queryByText('Password is required.')).not.toBeInTheDocument()
  })

  it('keeps email-format validation when a non-empty value is invalid', async () => {
    render(<LoginForm onSuccess={vi.fn()} />)

    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'not-an-email' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    await waitFor(() =>
      expect(screen.getByText('Enter a valid email address.')).toBeInTheDocument(),
    )
    expect(loginMock).not.toHaveBeenCalled()
  })
})
