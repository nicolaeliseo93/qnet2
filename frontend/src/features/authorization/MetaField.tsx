import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type {
  Control,
  ControllerRenderProps,
  FieldPath,
  FieldValues,
} from 'react-hook-form'
import {
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { useResourcePermissions } from '@/features/authorization/permissions'

interface MetaFieldRenderArgs<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
> {
  field: ControllerRenderProps<TFieldValues, TName>
  /** Forward to the input's `disabled` prop: true when hard-disabled or non-editable. */
  disabled: boolean
  /** Forward to the input's `readOnly` prop when it supports one (e.g. text inputs). */
  readOnly: boolean
}

interface MetaFieldProps<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key for this field (may differ from the RHF path). */
  metaKey: string
  label: ReactNode
  /** Overrides the automatic "not editable" hint shown under a locked field. */
  description?: ReactNode
  /**
   * Renders the actual control, exactly as an inline `FormField` render prop
   * would (own `<FormControl>` placement included) — this keeps composition
   * identical to the existing forms for controls whose interactive element
   * sits inside a wrapper (e.g. `<Select><FormControl>…</FormControl>…</Select>`).
   */
  children: (args: MetaFieldRenderArgs<TFieldValues, TName>) => ReactNode
}

/**
 * Metadata-driven wrapper around the existing `FormField`. UI-only: no
 * authorization is derived here, only read from `useResourcePermissions()`.
 * - `!visible` → renders nothing.
 * - otherwise → renders the field with `disabled`/`readOnly` forwarded to the
 *   caller's control and `required` forwarded to the label.
 */
export function MetaField<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
>({
  control,
  name,
  metaKey,
  label,
  description,
  children,
}: MetaFieldProps<TFieldValues, TName>) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const permission = fieldPermission(metaKey)

  if (!permission.visible) {
    return null
  }

  // A field that is not editable must be locked regardless of the raw
  // `disabled` flag: `readonly` fields are `editable: false` but not
  // necessarily `disabled: true` (see the spec's derivation rules).
  const disabled = permission.disabled || !permission.editable
  const readOnly = permission.readonly

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={permission.required}>{label}</FormLabel>
          {children({ field, disabled, readOnly })}
          {description ??
            (disabled ? (
              <FormDescription>{t('authorization.fieldNotEditable')}</FormDescription>
            ) : null)}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
