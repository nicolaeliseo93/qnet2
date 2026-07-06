import { describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useEntityDetail } from '@/hooks/use-entity-detail'

// Simulates the server: first read returns v1, every read after an "edit" returns v2.
let serverValue = 'v1'
const fetchEntity = vi.fn(async () => ({ id: 1, name: serverValue }))

function EditLoader() {
  const { data, isLoading } = useEntityDetail(['entity', 'detail', 1], fetchEntity)
  if (isLoading || !data) return <div>loading</div>
  // Mirrors RHF defaultValues: captured at mount of this child.
  return <FormLike name={data.name} />
}

function FormLike({ name }: { name: string }) {
  // defaultValue, like RHF — only the initial value matters.
  const [value] = useState(name)
  return <output data-testid="field">{value}</output>
}

function Harness() {
  const [open, setOpen] = useState(false)
  return (
    <div>
      <button onClick={() => setOpen((o) => !o)}>toggle</button>
      {open && <EditLoader />}
    </div>
  )
}

describe('reopen-after-edit freshness', () => {
  it('shows the persisted value on reopen', async () => {
    const queryClient = new QueryClient()
    render(
      <QueryClientProvider client={queryClient}>
        <Harness />
      </QueryClientProvider>,
    )

    // Open the edit form the first time.
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.getByTestId('field')).toHaveTextContent('v1'))

    // User edits + saves -> server now holds v2. Close the sheet.
    serverValue = 'v2'
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.queryByTestId('field')).toBeNull())

    // Reopen the edit form. Expect the fresh persisted value.
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.getByTestId('field')).toHaveTextContent('v2'))
  })
})
