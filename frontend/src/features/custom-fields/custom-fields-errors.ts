import type { FieldValues, Path } from 'react-hook-form'
import { rawKey, type CustomFieldDescriptor, type CustomFieldsFormShape } from '@/features/custom-fields/types'

/**
 * Builds the list of RHF paths a host form must pass to the existing
 * `applyServerValidationErrors` (`features/auth/form-errors`) so a 422 on
 * `custom_fields.<rawKey>` maps onto the matching `custom_fields.<rawKey>`
 * RHF field — no bespoke mapping needed, the backend error key and the RHF
 * path are identical by construction.
 */
export function customFieldErrorPaths<TFieldValues extends FieldValues & CustomFieldsFormShape>(
  fields: CustomFieldDescriptor[],
): Path<TFieldValues>[] {
  return fields.map((descriptor) => `custom_fields.${rawKey(descriptor.key)}` as Path<TFieldValues>)
}
