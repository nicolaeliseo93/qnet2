import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UserForm } from '@/features/users/user-form'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/* ----------------------------- module mocks ------------------------------- */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

/**
 * This suite is not about authorization metadata (covered by
 * `user-form-metadata.test.tsx`): every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty) so create/edit render
 * exactly as they did before spec 0004.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true },
}

vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// Enum options consumed by the personal-data card form (controlled, no network).
const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    {
      value: 'individual',
      label: 'Individual',
      color: null,
      icon: null,
      is_default: true,
      hidden_on_form: false,
    },
    {
      value: 'company',
      label: 'Company',
      color: null,
      icon: null,
      is_default: false,
      hidden_on_form: false,
    },
  ],
  personal_title: [],
  contact_type: [],
  locale: [
    {
      value: 'en',
      label: 'English',
      color: null,
      icon: null,
      is_default: true,
      hidden_on_form: false,
    },
    {
      value: 'it',
      label: 'Italiano',
      color: null,
      icon: null,
      is_default: false,
      hidden_on_form: false,
    },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

// Control the edit-mode card seed without hitting the network.
const personalDataData = vi.fn<() => PersonalDataCard | null | undefined>()
vi.mock('@/features/personal-data/use-personal-data', () => ({
  usePersonalDataByOwner: () => ({
    data: personalDataData(),
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

/* -------------------------------- helpers --------------------------------- */

function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function user(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    created_at: null,
    // `useUserFormMeta` is mocked above, so this value is never actually read;
    // present only to satisfy `UserFormMode`'s edit-variant type.
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    title: null,
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    personable_type: 'user',
    personable_id: 7,
    contacts: [
      {
        id: 5,
        type: 'email',
        label: 'Work',
        value: 'ada@work.com',
        is_primary: true,
        contactable_type: 'personal_data',
        contactable_id: 99,
        created_at: null,
      },
    ],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  createUserMock.mockResolvedValue(user())
  updateUserMock.mockResolvedValue(user())
})

/* --------------------------------- tests ---------------------------------- */

describe('UserForm — atomic personal data', () => {
  it('renders the always-active personal-data section in create mode', () => {
    render(
      <UserForm
        mode={{ type: 'create' }}
        onSuccess={() => {}}
        onCancel={() => {}}
      />,
      { wrapper: wrapper() },
    )

    // The card is active from the start: no add/remove affordance, the required
    // identity fields are rendered immediately.
    expect(screen.getByText('Personal data')).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Add personal data' }),
    ).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Last name/)).toBeInTheDocument()
  })

  it('submits one createUser call carrying the nested personal_data', async () => {
    render(
      <UserForm
        mode={{ type: 'create' }}
        onSuccess={() => {}}
        onCancel={() => {}}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Email/), {
      target: { value: 'ada@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/^Password/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), {
      target: { value: 'secret123' },
    })

    // No account `name` field: identity comes only from the card. Fill the
    // required individual fields (the user's name is derived server-side).
    fireEvent.change(screen.getByLabelText(/^First name/), {
      target: { value: 'Ada' },
    })
    fireEvent.change(screen.getByLabelText(/^Last name/), {
      target: { value: 'Lovelace' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock).not.toHaveBeenCalled()

    const payload = createUserMock.mock.calls[0][0]
    // No top-level `name` is sent; the backend derives users.name from the card.
    expect(payload.name).toBeUndefined()
    expect(payload.personal_data).toBeDefined()
    expect(payload.personal_data.type).toBe('individual')
    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
    expect(payload.personal_data.contacts).toEqual([])
    expect(payload.personal_data.addresses).toEqual([])
  })

  it('blocks the save until the required personal-data fields are filled', async () => {
    render(
      <UserForm
        mode={{ type: 'create' }}
        onSuccess={() => {}}
        onCancel={() => {}}
      />,
      { wrapper: wrapper() },
    )

    // All account fields valid, but the mandatory identity fields are left empty.
    fireEvent.change(screen.getByLabelText(/^Email/), {
      target: { value: 'grace@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/^Password/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), {
      target: { value: 'secret123' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    // The card is mandatory: the request must not fire, and an error is shown.
    await waitFor(() =>
      expect(
        screen.getByText('Complete the required personal data fields.'),
      ).toBeInTheDocument(),
    )
    expect(createUserMock).not.toHaveBeenCalled()
  })

  it('seeds the card in edit mode and submits the tree in one updateUser call', async () => {
    personalDataData.mockReturnValue(card())

    render(
      <UserForm
        mode={{ type: 'edit', user: user() }}
        onSuccess={() => {}}
        onCancel={() => {}}
      />,
      { wrapper: wrapper() },
    )

    // Seeded card fields are present in the form.
    await waitFor(() =>
      expect(screen.getByLabelText(/^First name/)).toHaveValue('Ada'),
    )
    expect(screen.getByText('ada@work.com')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock).not.toHaveBeenCalled()

    const [id, payload] = updateUserMock.mock.calls[0]
    expect(id).toBe(7)
    // The card is upserted by owner, so it carries no id; children do.
    expect(payload.personal_data.type).toBe('individual')
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.contacts[0].id).toBe(5)
    expect(payload.personal_data.contacts[0].value).toBe('ada@work.com')
  })
})
