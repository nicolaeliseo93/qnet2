import { describe, expect, it } from 'vitest'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import { buildCreatePayload, buildUpdatePayload } from '@/features/users/user-form-payload'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Spec 0008 AC-012: the payload builder omits non-editable fields/sections. */

/** Builds a resolver returning the permissive default, overridden per key. */
function resolverFrom(
  overrides: Record<string, Partial<PersonalDataFieldPermission>>,
): PersonalDataFieldPermissionResolver {
  return (key) => ({
    visible: true,
    editable: true,
    required: false,
    disabled: false,
    readonly: false,
    ...overrides[key],
  })
}

function draft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft(),
    first_name: 'Ada',
    last_name: 'Lovelace',
    contacts: [
      { _key: 'c1', id: 1, type: 'email', value: 'ada@example.com', label: null, is_primary: true },
    ],
    addresses: [
      {
        _key: 'a1',
        id: 1,
        label: null,
        line1: '221B Baker Street',
        line2: null,
        postal_code: null,
        city_id: null,
        province_id: null,
        state_id: null,
        country_id: null,
        is_primary: true,
      },
    ],
    ...overrides,
  }
}

/** Fixture password satisfying the schema's minimum length; not a real credential. */
const TEST_PASSWORD = 'x'.repeat(12)

const formValues: UserFormValues = {
  email: 'ada@example.com',
  locale: 'en',
  roles: [],
  password: TEST_PASSWORD,
  password_confirmation: TEST_PASSWORD,
}

function original(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload — personal-data gating (spec 0008)', () => {
  it('includes every field/section when no resolver is supplied (AC-013 parity)', () => {
    const payload = buildCreatePayload(formValues, draft())

    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.addresses).toHaveLength(1)
  })

  it('omits a scalar field the resolver marks non-editable', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({ 'personal_data.first_name': { editable: false } }),
    )

    expect('first_name' in payload.personal_data).toBe(false)
    // Editable fields are unaffected.
    expect(payload.personal_data.last_name).toBe('Lovelace')
  })

  it('omits the whole contacts/addresses key when the resolver marks the section non-editable', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({
        'personal_data.contacts': { editable: false },
        'personal_data.addresses': { editable: false },
      }),
    )

    expect('contacts' in payload.personal_data).toBe(false)
    expect('addresses' in payload.personal_data).toBe(false)
  })

  it('keeps editable fields/sections present', () => {
    const payload = buildCreatePayload(
      formValues,
      draft(),
      resolverFrom({ 'personal_data.tax_code': { editable: false } }),
    )

    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.addresses).toHaveLength(1)
    expect(payload.personal_data.first_name).toBe('Ada')
    expect('tax_code' in payload.personal_data).toBe(false)
  })
})

describe('buildUpdatePayload — personal-data gating (spec 0008)', () => {
  it('omits a non-editable scalar field from the PATCH personal_data tree', () => {
    const payload = buildUpdatePayload(
      { ...formValues, password: '', password_confirmation: '' },
      original(),
      draft(),
      resolverFrom({ 'personal_data.last_name': { editable: false } }),
    )

    expect(payload.personal_data).toBeDefined()
    expect('last_name' in (payload.personal_data ?? {})).toBe(false)
    expect(payload.personal_data?.first_name).toBe('Ada')
  })
})
