import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import { CUSTOM_FIELD_TYPES, type CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionIdentityFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
  entities: CustomFieldEntity[]
  /** `entity_type`/`key`/`type` are immutable once the field has values; the edit form locks them regardless (server re-enforces on any attempted change). */
  lockIdentity: boolean
}

/**
 * Identity fields of a custom field DEFINITION (spec AC-025): the
 * entity_type/key/type triad (locked on edit) plus label/description/help
 * text/placeholder/icon/group/tab/sort_order.
 */
export function DefinitionIdentityFields({ control, entities, lockIdentity }: DefinitionIdentityFieldsProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={SlidersHorizontal}
      title={t('customFields.form.sections.identity.title')}
      description={t('customFields.form.sections.identity.description')}
    >
      <MetaField control={control} name="entity_type" metaKey="entity_type" label={t('customFields.form.entityType')}>
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

      <MetaField control={control} name="key" metaKey="key" label={t('customFields.form.key')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              autoComplete="off"
              disabled={disabled || lockIdentity}
              readOnly={readOnly || lockIdentity}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="type" metaKey="type" label={t('customFields.form.type')}>
        {({ field, disabled }) => (
          <Select
            value={field.value}
            onValueChange={(next) => field.onChange(next as CustomFieldType)}
            disabled={disabled || lockIdentity}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {CUSTOM_FIELD_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`customFields.types.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      <MetaField control={control} name="label" metaKey="label" label={t('customFields.form.label')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="description" metaKey="description" label={t('customFields.form.description')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="help_text" metaKey="help_text" label={t('customFields.form.helpText')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="placeholder" metaKey="placeholder" label={t('customFields.form.placeholder')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="icon" metaKey="icon" label={t('customFields.form.icon')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" placeholder="tag" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="group" metaKey="group" label={t('customFields.form.group')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="tab" metaKey="tab" label={t('customFields.form.tab')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="sort_order" metaKey="sort_order" label={t('customFields.form.sortOrder')}>
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
