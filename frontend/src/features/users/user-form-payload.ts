import { draftToPayload, omitNonEditableFields } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type {
  CreateUserPayload,
  UpdateUserPayload,
  UserDetailWithPermissions,
} from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * Builds the create payload, always including password + confirmation, plus the
 * nested `personal_data` tree when a card was entered (ADR 0012). The tree omits
 * any scalar field/section `fieldPermission` marks non-editable (spec 0008 D2,
 * defense in depth alongside the backend's CHANGE-based guard).
 */
export function buildCreatePayload(
  values: UserFormValues,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): CreateUserPayload {
  return {
    email: values.email,
    locale: values.locale,
    roles: values.roles,
    password: values.password,
    password_confirmation: values.password_confirmation,
    personal_data: omitNonEditableFields(draftToPayload(profileDraft), fieldPermission),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original user. Password is included only when a new one was typed. The nested
 * `personal_data` tree is always sent when a card is present (authoritative sync),
 * so children added/edited/removed in the buffer persist in the one request; it
 * omits any scalar field/section `fieldPermission` marks non-editable (spec 0008 D2).
 */
export function buildUpdatePayload(
  values: UserFormValues,
  original: UserDetailWithPermissions,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): UpdateUserPayload {
  const payload: UpdateUserPayload = {}

  if (values.email !== original.email) {
    payload.email = values.email
  }
  if (values.locale !== original.locale) {
    payload.locale = values.locale
  }
  if (!sameRoles(values.roles, original.roles.map((role) => role.id))) {
    payload.roles = values.roles
  }
  if (values.password !== '') {
    payload.password = values.password
    payload.password_confirmation = values.password_confirmation
  }
  payload.personal_data = omitNonEditableFields(draftToPayload(profileDraft), fieldPermission)

  return payload
}

/** Order-insensitive comparison of two role-id lists. */
function sameRoles(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}
