import type {
  CreateVatRatePayload,
  UpdateVatRatePayload,
  VatRateDetail,
} from '@/features/vat-rates/types'
import type { VatRateFormValues } from '@/features/vat-rates/use-vat-rate-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: `name` + `rate` plus valued custom fields. */
export function buildCreatePayload(values: VatRateFormValues): CreateVatRatePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    // `rate` is validated non-null by the schema's required-value
    // superRefine before submit.
    rate: values.rate as number,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original VAT rate.
 */
export function buildUpdatePayload(
  values: VatRateFormValues,
  original: VatRateDetail,
): UpdateVatRatePayload {
  const payload: UpdateVatRatePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.rate !== original.rate) {
    // See buildCreatePayload: validated non-null by the schema's
    // required-value superRefine before submit.
    payload.rate = values.rate as number
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
