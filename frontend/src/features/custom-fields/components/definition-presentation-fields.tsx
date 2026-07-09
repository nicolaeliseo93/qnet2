import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Paintbrush } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { IconPicker } from '@/components/icon-picker'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl, FormDescription, useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionPresentationFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
}

/**
 * "Presentation" group of a custom field DEFINITION: how the field LOOKS to the
 * end user — description, help text, placeholder, icon, group and sort order.
 * Rendered AFTER the type-dependent settings so the visual polish comes last.
 * (`tab` is intentionally not surfaced: the generic renderer has no tabs UI, so
 * it only ever affected ordering — dropped to avoid a control that does nothing.)
 */
export function DefinitionPresentationFields({ control }: DefinitionPresentationFieldsProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Paintbrush}
      title={t('customFields.form.sections.presentation.title')}
      description={t('customFields.form.sections.presentation.description')}
    >
      <MetaField
        control={control}
        name="description"
        metaKey="description"
        label={t('customFields.form.description')}
        description={<FormDescription>{t('customFields.form.descriptionHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="help_text"
        metaKey="help_text"
        label={t('customFields.form.helpText')}
        description={<FormDescription>{t('customFields.form.helpTextHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="placeholder"
        metaKey="placeholder"
        label={t('customFields.form.placeholder')}
        description={<FormDescription>{t('customFields.form.placeholderHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="icon"
        metaKey="icon"
        label={t('customFields.form.icon')}
        description={<FormDescription>{t('customFields.form.iconHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <IconPickerControl
            value={field.value}
            onChange={field.onChange}
            disabled={disabled}
            readOnly={readOnly}
          />
        )}
      </MetaField>

      <MetaField
        control={control}
        name="group"
        metaKey="group"
        label={t('customFields.form.group')}
        description={<FormDescription>{t('customFields.form.groupHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="sort_order"
        metaKey="sort_order"
        label={t('customFields.form.sortOrder')}
        description={<FormDescription>{t('customFields.form.sortOrderHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="number"
              disabled={disabled}
              readOnly={readOnly}
              value={field.value}
              onChange={(event) => field.onChange(event.target.value === '' ? 0 : Number(event.target.value))}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}

interface IconPickerControlProps {
  value: string
  onChange: (name: string) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Bridges the shared `IconPicker` into the RHF/`FormItem` context: reads the
 * accessible-error triad ids via `useFormField()` (one level inside the
 * `MetaField` render prop, same reason as `CustomFieldControlBridge`) and
 * supplies the localized picker labels.
 */
function IconPickerControl({ value, onChange, disabled, readOnly }: IconPickerControlProps) {
  const { t } = useTranslation()
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()

  return (
    <IconPicker
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
      labels={{
        placeholder: t('customFields.form.iconPickerPlaceholder'),
        searchPlaceholder: t('customFields.form.iconSearchPlaceholder'),
        empty: t('customFields.form.iconEmpty'),
        clearLabel: t('customFields.form.iconClear'),
      }}
    />
  )
}
