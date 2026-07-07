import { isAddressPresent } from '@/features/company-sites/company-site-schema'
import type {
  BankDraft,
  CompanySiteDetailWithPermissions,
  CreateCompanySiteAddressPayload,
  CreateCompanySiteBankPayload,
  CreateCompanySitePayload,
  UpdateCompanySitePayload,
} from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

/** Builds the nested address payload once the block is known to carry a value. */
function toAddressPayload(
  address: CompanySiteFormValues['address'],
): CreateCompanySiteAddressPayload {
  return {
    line1: address.line1 ?? '',
    line2: address.line2 || null,
    postal_code: address.postal_code || null,
    country_id: address.country_id ?? null,
    state_id: address.state_id ?? null,
    province_id: address.province_id ?? null,
    city_id: address.city_id ?? null,
  }
}

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
 * Builds the create payload. The address block is omitted entirely when the
 * user left every one of its fields blank (a site may have no address, same
 * rule as companies); `banks`/`default_bank_id` are omitted when the buffer
 * is empty (nothing to create, nothing to default to).
 */
export function buildCreatePayload(
  values: CompanySiteFormValues,
  banks: BankDraft[],
): CreateCompanySitePayload {
  return {
    name: values.name,
    email: values.email,
    fiscal_code: values.fiscal_code || null,
    vat_number: values.vat_number || null,
    phone: values.phone || null,
    pec: values.pec || null,
    fax: values.fax || null,
    notes: values.notes || null,
    ...(isAddressPresent(values.address) ? { address: toAddressPayload(values.address) } : {}),
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
 * original site. The address, when present and changed, is always sent in
 * full (it fully rewrites the site's single address server-side); `banks` is
 * sent — authoritatively, the whole buffer — only when it actually differs
 * from the original collection (AC-019). "Altro" fields and `is_default` are
 * never part of this payload (read-only / dedicated `set-default` action).
 */
export function buildUpdatePayload(
  values: CompanySiteFormValues,
  original: CompanySiteDetailWithPermissions,
  banks: BankDraft[],
): UpdateCompanySitePayload {
  const payload: UpdateCompanySitePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.email !== original.email) {
    payload.email = values.email
  }

  assignIfChanged(payload, 'fiscal_code', values.fiscal_code || null, original.fiscal_code)
  assignIfChanged(payload, 'vat_number', values.vat_number || null, original.vat_number)
  assignIfChanged(payload, 'phone', values.phone || null, original.phone)
  assignIfChanged(payload, 'pec', values.pec || null, original.pec)
  assignIfChanged(payload, 'fax', values.fax || null, original.fax)
  assignIfChanged(payload, 'notes', values.notes || null, original.notes)

  if (isAddressPresent(values.address) && addressChanged(values.address, original.address)) {
    payload.address = toAddressPayload(values.address)
  }

  if (banksChanged(banks, original.banks)) {
    payload.banks = toBanksPayload(banks)
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
 * Whether the form's address block differs from the original — compares only
 * the ids the payload actually carries (the resolved names are read-only
 * detail-view labels, not part of the request).
 */
function addressChanged(
  address: CompanySiteFormValues['address'],
  original: CompanySiteDetailWithPermissions['address'],
): boolean {
  if (!original) {
    return true
  }
  const next = toAddressPayload(address)
  return (
    next.line1 !== original.line1 ||
    next.line2 !== original.line2 ||
    next.postal_code !== original.postal_code ||
    next.country_id !== original.country_id ||
    next.state_id !== original.state_id ||
    next.province_id !== original.province_id ||
    next.city_id !== original.city_id
  )
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
