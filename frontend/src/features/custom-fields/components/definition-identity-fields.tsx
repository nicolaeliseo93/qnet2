import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Paintbrush, SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { IconPicker } from '@/components/icon-picker'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl, FormDescription, useFormField } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { DefinitionTypePicker } from '@/features/custom-fields/components/definition-type-picker'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionIdentityFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
  entities: CustomFieldEntity[]
  /** `entity_type`/`key`/`type` are immutable once the field has values; the edit form locks them regardless (server re-enforces on any attempted change). */
  lockIdentity: boolean
}

/** Always-visible one-line helper under a field, explaining its purpose. */
function Help({ children }: { children: string }) {
  return <FormDescription>{children}</FormDescription>
}

/**
 * Identity fields of a custom field DEFINITION (spec AC-025), reorganised into
 * two groups so the form reads top-to-bottom: "Base" (what the field IS —
 * module, type, key, label) then "Presentation" (how it LOOKS to the end user).
 * Every field carries an inline helper so a non-technical admin understands its
 * purpose; the `type` gets its own explanatory picker and `icon` a searchable
 * lucide picker.
 */
export function DefinitionIdentityFields({ control, entities, lockIdentity }: DefinitionIdentityFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      <FormSection
        icon={SlidersHorizontal}
        title={t('customFields.form.sections.base.title')}
        description={t('customFields.form.sections.base.description')}
      >
        <MetaField
          control={control}
          name="entity_type"
          metaKey="entity_type"
          label={t('customFields.form.entityType')}
          description={<Help>{t('customFields.form.entityTypeHelp')}</Help>}
        >
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={field.onChange} disabled={disabled || lockIdentity}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('customFields.form.entityTypePlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {entities.map((entity) => (
                  <SelectItem key={entity.entity_type} value={entity.entity_type}>
                    {t(entity.label)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>

        <DefinitionTypePicker control={control} lockIdentity={lockIdentity} />

        <MetaField
          control={control}
          name="key"
          metaKey="key"
          label={t('customFields.form.key')}
          description={<Help>{t('customFields.form.keyHelp')}</Help>}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                autoComplete="off"
                placeholder="loyalty_tier"
                disabled={disabled || lockIdentity}
                readOnly={readOnly || lockIdentity}
                {...field}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="label"
          metaKey="label"
          label={t('customFields.form.label')}
          description={<Help>{t('customFields.form.labelHelp')}</Help>}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>
      </FormSection>

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
          description={<Help>{t('customFields.form.descriptionHelp')}</Help>}
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
          description={<Help>{t('customFields.form.helpTextHelp')}</Help>}
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
          description={<Help>{t('customFields.form.placeholderHelp')}</Help>}
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
          description={<Help>{t('customFields.form.iconHelp')}</Help>}
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
          description={<Help>{t('customFields.form.groupHelp')}</Help>}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="tab"
          metaKey="tab"
          label={t('customFields.form.tab')}
          description={<Help>{t('customFields.form.tabHelp')}</Help>}
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
          description={<Help>{t('customFields.form.sortOrderHelp')}</Help>}
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
    </>
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
