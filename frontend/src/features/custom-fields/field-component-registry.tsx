import type { ComponentType } from 'react'
import { BooleanFieldControl } from '@/features/custom-fields/components/boolean-field-control'
import { EnumFieldControl } from '@/features/custom-fields/components/enum-field-control'
import { NumberFieldControl } from '@/features/custom-fields/components/number-field-control'
import { RelationFieldControl } from '@/features/custom-fields/components/relation-field-control'
import { TextFieldControl } from '@/features/custom-fields/components/text-field-control'
import { TextareaFieldControl } from '@/features/custom-fields/components/textarea-field-control'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'
import type { CustomFieldType } from '@/features/custom-fields/types'

export type { CustomFieldControlProps }

/**
 * The single seam that maps a backend `type` to a frontend control (spec 0021
 * OCP constraint: "1 FieldTypeHandler backend + 1 registry entry frontend,
 * zero other changes"). Adding a new custom field type means:
 *   1. a new `<X>FieldControl.tsx` implementing `CustomFieldControlProps`;
 *   2. one new entry below.
 * `CustomFieldsSection` never branches on `type` itself — it only looks up
 * this map.
 */
export const CUSTOM_FIELD_COMPONENT_REGISTRY: Record<
  CustomFieldType,
  ComponentType<CustomFieldControlProps>
> = {
  text: TextFieldControl,
  textarea: TextareaFieldControl,
  integer: NumberFieldControl,
  decimal: NumberFieldControl,
  boolean: BooleanFieldControl,
  enum: EnumFieldControl,
  relation: RelationFieldControl,
}
