import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { useReferentDuplicateCheck } from '@/features/referents/use-referent-duplicate-check'
import type { ReferentFormMode } from '@/features/referents/types'

const checkReferentDuplicatesMock = vi.fn()

vi.mock('@/features/referents/duplicate-check-api', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/referents/duplicate-check-api')
  >('@/features/referents/duplicate-check-api')
  return {
    ...actual,
    checkReferentDuplicates: (...args: unknown[]) => checkReferentDuplicatesMock(...args),
  }
})

const CREATE_MODE: ReferentFormMode = { type: 'create' }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function draftWithEmail(email: string): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft(),
    contacts: [
      { _key: 'k1', type: 'email', value: email, label: null, is_primary: true },
    ],
  }
}

beforeEach(() => {
  checkReferentDuplicatesMock.mockReset()
  checkReferentDuplicatesMock.mockResolvedValue({ matches: [] })
})

describe('useReferentDuplicateCheck (AC-007, AC-008)', () => {
  it('does not call the API while every criterion is empty', async () => {
    renderHook(
      () => useReferentDuplicateCheck({ mode: CREATE_MODE, profileDraft: emptyPersonalDataDraft() }),
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkReferentDuplicatesMock).not.toHaveBeenCalled()
  })

  it('debounces and calls the API with the normalized email criterion once one is filled', async () => {
    checkReferentDuplicatesMock.mockResolvedValue({
      matches: [{ referent_id: 1, name: 'Mario Rossi', matched_on: ['email'] }],
    })

    const { result, rerender } = renderHook(
      ({ profileDraft }: { profileDraft: PersonalDataDraft }) =>
        useReferentDuplicateCheck({ mode: CREATE_MODE, profileDraft }),
      { wrapper: wrapper(), initialProps: { profileDraft: emptyPersonalDataDraft() } },
    )

    rerender({ profileDraft: draftWithEmail(' Mario.Rossi@Example.com ') })

    await waitFor(() => expect(checkReferentDuplicatesMock).toHaveBeenCalledTimes(1))
    expect(checkReferentDuplicatesMock).toHaveBeenCalledWith({
      tax_code: undefined,
      contacts: [{ type: 'email', value: 'Mario.Rossi@Example.com' }],
    })
    await waitFor(() => expect(result.current.matches).toHaveLength(1))
    expect(result.current.matches[0].name).toBe('Mario Rossi')
  })

  it('never runs in edit mode', async () => {
    renderHook(
      () =>
        useReferentDuplicateCheck({
          mode: { type: 'edit', referent: {} as never },
          profileDraft: draftWithEmail('mario.rossi@example.com'),
        }),
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkReferentDuplicatesMock).not.toHaveBeenCalled()
  })

  it('clears the matches once the fields go back to empty', async () => {
    checkReferentDuplicatesMock.mockResolvedValue({
      matches: [{ referent_id: 1, name: 'Mario Rossi', matched_on: ['email'] }],
    })

    const { result, rerender } = renderHook(
      ({ profileDraft }: { profileDraft: PersonalDataDraft }) =>
        useReferentDuplicateCheck({ mode: CREATE_MODE, profileDraft }),
      { wrapper: wrapper(), initialProps: { profileDraft: draftWithEmail('mario.rossi@example.com') } },
    )

    await waitFor(() => expect(result.current.matches).toHaveLength(1))

    rerender({ profileDraft: emptyPersonalDataDraft() })

    await waitFor(() => expect(result.current.matches).toHaveLength(0))
  })
})
