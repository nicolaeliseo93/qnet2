import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { EnumOption } from '@/features/config/types'
import { usePersonalDataByOwner } from '@/features/personal-data/use-personal-data'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  personal_title: [],
}

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

// The server-side card. A "save" mutates this; reopening must reflect it.
let serverCard = {
  id: 1,
  type: 'individual',
  first_name: 'Nicola',
  last_name: 'Eliseo',
  company_name: null,
  title: null,
  tax_code: null,
  vat_number: null,
  sdi_code: null,
  birth_date: null,
  contacts: [],
  addresses: [],
}
vi.mock('@/features/personal-data/api', () => ({
  fetchCardByOwner: vi.fn(async () => ({ ...serverCard })),
}))

const SEED_PENDING = Symbol('seed-pending')

// Mirrors the real seed-once + gate flow of useUserForm + user-form-body.
function IdentityCard() {
  const profileQuery = usePersonalDataByOwner({ type: 'user', id: 1 }, true)
  const [draft, setDraft] = useState<PersonalDataDraft>(emptyPersonalDataDraft)
  const [seededFrom, setSeededFrom] = useState<unknown>(SEED_PENDING)

  if (profileQuery.data !== undefined && seededFrom !== profileQuery.data) {
    setSeededFrom(profileQuery.data)
    setDraft(profileQuery.data ? cardToDraft(profileQuery.data) : emptyPersonalDataDraft())
  }

  // The real gate (user-form-body.tsx): waits for the on-open refetch.
  const isLoading = profileQuery.isPending || profileQuery.isFetching
  if (isLoading) return <div>loading</div>
  return <PersonalDataCardForm value={draft} onChange={setDraft} />
}

function Harness() {
  const [open, setOpen] = useState(false)
  return (
    <div>
      <button onClick={() => setOpen((o) => !o)}>toggle</button>
      {open && <IdentityCard />}
    </div>
  )
}

describe('user edit form freshness on reopen (personal-data card)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the persisted first name on reopen', async () => {
    const queryClient = new QueryClient()
    render(
      <QueryClientProvider client={queryClient}>
        <Harness />
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() =>
      expect(screen.getByLabelText(/first name/i)).toHaveValue('Nicola'),
    )

    // Save + close: server now holds the edited name (no cache invalidation).
    serverCard = { ...serverCard, first_name: 'Nicola2' }
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() => expect(screen.queryByLabelText(/first name/i)).toBeNull())

    // Reopen the form: the on-open refetch must repopulate the card with the
    // persisted value, not the stale cached snapshot from the first open.
    fireEvent.click(screen.getByText('toggle'))
    await waitFor(() =>
      expect(screen.getByLabelText(/first name/i)).toHaveValue('Nicola2'),
    )
  })
})
