import { fireEvent, render, screen } from '@testing-library/react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { ConfigGate } from '@/features/config/config-gate'
import { useConfig } from '@/features/config/use-config'

// The gate's only job is to read the config query state and decide what to
// mount, so we drive it by mocking useConfig rather than wiring a real client.
vi.mock('@/features/config/use-config', () => ({
  useConfig: vi.fn(),
}))

const mockedUseConfig = vi.mocked(useConfig)

type ConfigQueryResult = ReturnType<typeof useConfig>

function mockConfigState(state: Partial<ConfigQueryResult>): void {
  mockedUseConfig.mockReturnValue(state as ConfigQueryResult)
}

const SENTINEL = 'protected-children'

function renderGate() {
  return render(
    <ConfigGate>
      <div>{SENTINEL}</div>
    </ConfigGate>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ConfigGate', () => {
  it('shows the boot loader and withholds children while pending', () => {
    mockConfigState({ isPending: true, isError: false, isSuccess: false })
    renderGate()

    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText(SENTINEL)).not.toBeInTheDocument()
  })

  it('shows the error screen with a retry button and withholds children on error', () => {
    const refetch = vi.fn()
    mockConfigState({ isPending: false, isError: true, isSuccess: false, refetch })
    renderGate()

    expect(screen.getByText('Unable to start the application')).toBeInTheDocument()
    expect(screen.queryByText(SENTINEL)).not.toBeInTheDocument()

    const retry = screen.getByRole('button', { name: 'Retry' })
    fireEvent.click(retry)
    expect(refetch).toHaveBeenCalledTimes(1)
  })

  it('renders children once the config has loaded successfully', () => {
    mockConfigState({ isPending: false, isError: false, isSuccess: true })
    renderGate()

    expect(screen.getByText(SENTINEL)).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
