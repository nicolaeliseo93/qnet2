import {
  cardToDraft,
  draftToPayload,
  emptyPersonalDataDraft,
  omitNonEditableFields,
  type PersonalDataPayload,
} from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type {
  BankDraft,
  CompanySiteDetailWithPermissions,
  CreateCompanySiteBankPayload,
  CreateCompanySitePayload,
  UpdateCompanySitePayload,
} from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

/**
 * The card is always a `company` and carries at most one address (spec 0020):
 * the UI already caps the list, but the payload defends the invariant too so a
 * stale buffer can never send more than one address to the backend.
 */
const MAX_ADDRESSES = 1

/** Maps the buffered banks draft to the nested `banks[]` wire payload. */
function toBanksPayload(banks: BankDraft[]): CreateCompanySiteBankPayload[] {
  return banks.map((bank) => ({
    ...(bank.id !== undefined ? { id: bank.id } : {}),
    name: bank.name,
    iban: bank.iban,
    notes: bank.notes,
  }))
}

/**
 * Builds the gated, address-capped `personal_data` tree from the buffered
 * draft: `omitNonEditableFields` drops any scalar field/section the resolver
 * marks non-editable (spec 0008, defense in depth alongside the backend), then
 * the address list is truncated to the single one the backend accepts.
 */
function toPersonalDataPayload(
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): PersonalDataPayload {
  const payload = omitNonEditableFields(draftToPayload(profileDraft), fieldPermission)
  if (payload.addresses && payload.addresses.length > MAX_ADDRESSES) {
    return { ...payload, addresses: payload.addresses.slice(0, MAX_ADDRESSES) }
  }
  return payload
}

/**
 * Builds the create payload: the site's own scalars (`name` required, `notes`)
 * plus the nested `personal_data` tree (REQUIRED, always `type: 'company'`) and
 * the Impostazioni-tab fields. `banks`/`default_bank_id` are omitted when the
 * buffer is empty (nothing to create, nothing to default to).
 */
export function buildCreatePayload(
  values: CompanySiteFormValues,
  banks: BankDraft[],
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): CreateCompanySitePayload {
  return {
    name: values.name,
    notes: values.notes || null,
    personal_data: toPersonalDataPayload(profileDraft, fieldPermission),
    ...(banks.length > 0 ? { banks: toBanksPayload(banks) } : {}),
    default_bank_id: values.default_bank_id,
    responsible_rda_id: values.responsible_rda_id,
    responsible_tickets_id: values.responsible_tickets_id,
    responsible_validation_contracts_id: values.responsible_validation_contracts_id,
    responsible_validation_contracts_two_id: values.responsible_validation_contracts_two_id,
    proforma_progressive: values.proforma_progressive,
    invoice_progressive: values.invoice_progressive,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original site. `banks` is sent — authoritatively, the whole buffer — only
 * when it actually differs from the original collection (AC-019); the nested
 * `personal_data` tree is included only when the buffered draft differs from
 * the one seeded from `original.personal_data` (`draftToPayload` has a fixed
 * key order, so a `JSON.stringify` compare is a reliable deep-equal here,
 * mirroring the Registries module). "Altro" fields and `is_default` are never
 * part of this payload (read-only / dedicated `set-default` action).
 */
export function buildUpdatePayload(
  values: CompanySiteFormValues,
  original: CompanySiteDetailWithPermissions,
  banks: BankDraft[],
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): UpdateCompanySitePayload {
  const payload: UpdateCompanySitePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  assignIfChanged(payload, 'notes', values.notes || null, original.notes)

  if (banksChanged(banks, original.banks)) {
    payload.banks = toBanksPayload(banks)
  }

  const originalDraft = original.personal_data
    ? cardToDraft(original.personal_data)
    : emptyPersonalDataDraft('company')
  const nextPayload = draftToPayload(profileDraft)
  if (JSON.stringify(nextPayload) !== JSON.stringify(draftToPayload(originalDraft))) {
    payload.personal_data = toPersonalDataPayload(profileDraft, fieldPermission)
  }

  assignIfChanged(payload, 'default_bank_id', values.default_bank_id, original.default_bank_id)
  assignIfChanged(
    payload,
    'responsible_rda_id',
    values.responsible_rda_id,
    original.responsible_rda_id,
  )
  assignIfChanged(
    payload,
    'responsible_tickets_id',
    values.responsible_tickets_id,
    original.responsible_tickets_id,
  )
  assignIfChanged(
    payload,
    'responsible_validation_contracts_id',
    values.responsible_validation_contracts_id,
    original.responsible_validation_contracts_id,
  )
  assignIfChanged(
    payload,
    'responsible_validation_contracts_two_id',
    values.responsible_validation_contracts_two_id,
    original.responsible_validation_contracts_two_id,
  )
  assignIfChanged(
    payload,
    'proforma_progressive',
    values.proforma_progressive,
    original.proforma_progressive,
  )
  assignIfChanged(
    payload,
    'invoice_progressive',
    values.invoice_progressive,
    original.invoice_progressive,
  )

  return payload
}

/** Sets `payload[key] = next` only when it differs from the original value. */
function assignIfChanged<K extends keyof UpdateCompanySitePayload>(
  payload: UpdateCompanySitePayload,
  key: K,
  next: UpdateCompanySitePayload[K],
  original: UpdateCompanySitePayload[K],
): void {
  if (next !== original) {
    payload[key] = next
  }
}

/**
 * Whether the buffered banks differ from the original collection: a
 * different count, a different set of ids (add/remove), or a changed
 * name/iban/notes on a kept row.
 */
function banksChanged(
  banks: BankDraft[],
  original: CompanySiteDetailWithPermissions['banks'],
): boolean {
  if (banks.length !== original.length) {
    return true
  }
  const originalById = new Map(original.map((bank) => [bank.id, bank]))
  return banks.some((bank) => {
    if (bank.id === undefined) {
      return true
    }
    const match = originalById.get(bank.id)
    return (
      !match ||
      bank.name !== match.name ||
      bank.iban !== match.iban ||
      bank.notes !== match.notes
    )
  })
}
