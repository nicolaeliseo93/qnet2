import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control, FieldPath } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { CUSTOM_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/field-component-registry'
import type { CustomFieldDescriptor, CustomFieldValue } from '@/features/custom-fields/types'
import { toCustomFieldDescriptor } from '@/features/request-management/request-attribute-adapter'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface RequestDynamicFieldsProps {
  control: Control<RequestWorkFormValues>
  /** Union, dedup by `code`, of the effective Attributes of every product line (spec 0049). */
  attributes: ApplicableAttribute[]
}

/**
 * The dynamic fields section: one control per applicable Attribute (AC-061),
 * dispatched by `type` through the existing `CUSTOM_FIELD_COMPONENT_REGISTRY`
 * via `toCustomFieldDescriptor` — mirrors `CustomFieldsSection`'s own
 * `MetaField` + registry bridge exactly, so required/enum/a11y behave
 * identically to every other custom-field-backed form (AC-063).
 */
export function RequestDynamicFields({ control, attributes }: RequestDynamicFieldsProps) {
  const { t } = useTranslation()
  const title = t('requestManagement.workPanel.dynamicFields.title', { defaultValue: 'Additional information' })

  if (attributes.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <p className="text-sm text-muted-foreground">
          {t('requestManagement.workPanel.dynamicFields.empty', {
            defaultValue: 'No additional fields for this opportunity.',
          })}
        </p>
      </FormSection>
    )
  }

  const sorted = [...attributes].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <FormSection icon={SlidersHorizontal} title={title}>
      {sorted.map((attribute) => (
        <RequestDynamicField key={attribute.code} control={control} attribute={attribute} />
      ))}
    </FormSection>
  )
}

interface RequestDynamicFieldProps {
  control: Control<RequestWorkFormValues>
  attribute: ApplicableAttribute
}

/** One Attribute-backed field, gated by `<MetaField>` (single `attribute_values` permission key) and rendered via the registry. */
function RequestDynamicField({ control, attribute }: RequestDynamicFieldProps) {
  const name = `attribute_values.${attribute.code}` as FieldPath<RequestWorkFormValues>
  const descriptor = toCustomFieldDescriptor(attribute)

  return (
    <MetaField
      control={control}
      name={name}
      metaKey="attribute_values"
      label={attribute.name}
      description={attribute.help_text}
      required={attribute.is_required}
    >
      {({ field, disabled, readOnly }) => (
        <RequestAttributeControlBridge
          descriptor={descriptor}
          value={field.value as CustomFieldValue}
          onChange={field.onChange as (value: CustomFieldValue) => void}
          disabled={disabled}
          readOnly={readOnly}
        />
      )}
    </MetaField>
  )
}

interface RequestAttributeControlBridgeProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Resolves the accessible-error triad (frontend.md §10) via `useFormField()`
 * — one level inside `<MetaField>`'s render prop, since `<FormControl>`'s
 * automatic id injection only reaches its immediate JSX child (mirrors
 * `CustomFieldsSection`'s own bridge).
 */
function RequestAttributeControlBridge({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
}: RequestAttributeControlBridgeProps) {
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()
  const FieldControl = CUSTOM_FIELD_COMPONENT_REGISTRY[descriptor.type]

  return (
    <FieldControl
      descriptor={descriptor}
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
    />
  )
}
