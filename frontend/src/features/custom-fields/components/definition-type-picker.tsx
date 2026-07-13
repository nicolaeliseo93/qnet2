import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import { CUSTOM_FIELD_TYPES, type CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionTypePickerProps {
  control: Control<FieldDefinitionFormValues>
  /** `type` is immutable once the field has values; the edit form locks it. */
  lockIdentity: boolean
}

/**
 * The `type` selector, shared by the custom field definition form AND the
 * attribute form (spec 0017/0021: same 13-type `FieldTypeRegistry`) — it
 * governs every other section of the form. Each option carries its icon + a
 * one-line description, and an always-visible callout under the select explains
 * the currently-selected type with a concrete example (spec AC-025 UX refactor).
 */
export function DefinitionTypePicker({ control, lockIdentity }: DefinitionTypePickerProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="type" metaKey="type" label={t('customFields.form.type')}>
      {({ field, disabled }) => {
        const selected = field.value
        const SelectedIcon = FIELD_TYPE_ICONS[selected]
        return (
          <>
            <Select
              value={selected}
              onValueChange={(next) => field.onChange(next as CustomFieldType)}
              disabled={disabled || lockIdentity}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {CUSTOM_FIELD_TYPES.map((type) => {
                  const Icon = FIELD_TYPE_ICONS[type]
                  return (
                    <SelectItem key={type} value={type}>
                      <span className="flex items-center gap-2">
                        <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        {t(`customFields.types.${type}`)}
                      </span>
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>

            <div className="rounded-lg border bg-muted/40 p-3 text-xs">
              <p className="flex items-center gap-1.5 font-medium text-foreground">
                <SelectedIcon className="size-3.5 shrink-0 text-primary" aria-hidden="true" />
                {t(`customFields.types.${selected}`)}
              </p>
              <p className="mt-1 text-muted-foreground">
                {t(`customFields.typeInfo.${selected}.desc`)}
              </p>
              <p className="mt-1 text-muted-foreground">
                <span className="font-medium text-foreground">
                  {t('customFields.form.typeExampleLabel')}:
                </span>{' '}
                {t(`customFields.typeInfo.${selected}.example`)}
              </p>
            </div>
          </>
        )
      }}
    </MetaField>
  )
}
