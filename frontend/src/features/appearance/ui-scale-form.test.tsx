import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UiScaleForm } from '@/features/appearance/ui-scale-form'

/**
 * The appearance section saves the UI scale on its own — a partial PATCH
 * /auth/me carrying only `ui_scale` — and previews live through the shared
 * UiScaleProvider (here mocked so scale/setScale are controllable).
 */

const updateProfileMock = vi.fn()
vi.mock('@/features/auth/api', () => ({
  updateProfile: (...args: unknown[]) => updateProfileMock(...args),
}))

const setScaleMock = vi.fn()
const currentScale = vi.fn<() => number>()
vi.mock('@/features/appearance/ui-scale-context', () => ({
  useUiScale: () => ({
    scale: currentScale(),
    factor: 1,
    setScale: setScaleMock,
  }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProfileMock.mockReset()
  setScaleMock.mockReset()
  currentScale.mockReset()
  currentScale.mockReturnValue(60)
  updateProfileMock.mockResolvedValue({})
})

describe('UiScaleForm', () => {
  it('shows the current scale as a percentage preview (60 -> 110%)', () => {
    render(<UiScaleForm />, { wrapper: wrapper() })

    expect(screen.getByText('110%')).toBeInTheDocument()
  })

  it('saves only ui_scale via a partial PATCH /auth/me', async () => {
    render(<UiScaleForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(updateProfileMock).toHaveBeenCalledTimes(1))
    expect(updateProfileMock.mock.calls[0][0]).toEqual({ ui_scale: 60 })
  })

  it('restores the 100% default in one click', async () => {
    render(<UiScaleForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Restore defaults' }))

    expect(setScaleMock).toHaveBeenCalledWith(40)
    await waitFor(() =>
      expect(updateProfileMock).toHaveBeenCalledWith({ ui_scale: 40 }),
    )
  })
})
