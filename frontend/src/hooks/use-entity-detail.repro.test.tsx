import { describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'

// Mirrors usePersonalDataByOwner: a plain useQuery, NO staleTime/refetchOnMount overrides.
let serverCard = { id: 9, first_name: 'Mario' }
const fetchCard = vi.fn(async () => ({ ...serverCard }))

function usePersonalDataByOwner(enabled: boolean) {
  return useQuery({ queryKey: ['personal-data', 'user', 9], queryFn: fetchCard, enabled })
}

const SEED_PENDING = Symbol('seed-pending')

// Mirrors the relevant part of useUserForm: seed a local draft ONCE from the query.
function useMiniUserForm() {
  const query = usePersonalDataByOwner(true)
  const [draft, setDraft] = useState<{ first_name: string }>({ first_name: '' })
  const [seededFrom, setSeededFrom] = useState<unknown>(SEED_PENDING)

  if (query.data !== undefined && seededFrom !== query.data) {
    setSeededFrom(query.data)
    setDraft({ first_name: query.data.first_name })
  }
  return draft
}

function MiniForm() {
  const draft = useMiniUserForm()
  return <output data-testid="name">{draft.first_name}</output>
}

function Harness() {
  const [open, setOpen] = useState(false)
  return (
    <div>
      <button onClick={() => setOpen((o) => !o)}>toggle</button>
      {open && <MiniForm />}
    </div>
  )
}

describe('personal-data card freshness on reopen', () => {
  it('shows the persisted name on reopen', async () => {
    const queryClient = new QueryClient()
    render(
      <QueryClientProvider client={queryClient}>
        <Harness />
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.getByTestId('name')).toHaveTextContent('Mario'))

    // Edit + save -> server now holds the new name. Close the form (NO cache invalidation, like useUserForm).
    serverCard = { id: 9, first_name: 'Luigi' }
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.queryByTestId('name')).toBeNull())

    // Reopen.
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.getByTestId('name')).toHaveTextContent('Luigi'))
  })
})
