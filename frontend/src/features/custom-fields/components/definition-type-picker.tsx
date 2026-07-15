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

interface DefinitionTypePickerProps<T extends FieldDefinitionFormValues> {
  control: Control<T>
  /** `type` is immutable once the field has values; the edit form locks it. */
  lockIdentity: boolean
}

/**
 * The `type` selector, shared by the custom field definition form AND the
 * attribute form (spec 0017/0021: same 13-type `FieldTypeRegistry`) — it
 * governs every other section of the form. Each option carries its icon + a
 * one-line description, and an always-visible callout under the select explains
 * the currently-selected type with a concrete example (spec AC-025 UX refactor).
 *
 * Generic over `T` (each host form's own, wider values type) rather than
 * fixed to `FieldDefinitionFormValues`: RHF's `Control<T>` is not covariant,
 * so a fixed `Control<FieldDefinitionFormValues>` prop rejects every concrete
 * host control (which extends it with `code`/`name`/`custom_fields`, etc.).
 */
export function DefinitionTypePicker<T extends FieldDefinitionFormValues>({
  control,
  lockIdentity,
}: DefinitionTypePickerProps<T>) {
  const { t } = useTranslation()
  // `T` only ever ADDS fields on top of `FieldDefinitionFormValues` (never
  // narrows/reshapes them), so every path this component reads (`type`) is
  // guaranteed present and identically shaped for any `T`. RHF's `Path<T>`
  // can't express that guarantee for a generic, recursively-conditional
  // type, so the implementation narrows to the shared base once, here,
  // rather than casting per field.
  const baseControl = control as unknown as Control<FieldDefinitionFormValues>

  return (
    <MetaField control={baseControl} name="type" metaKey="type" label={t('customFields.form.type')}>
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
