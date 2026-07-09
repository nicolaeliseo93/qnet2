import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription } from '@/components/ui/form'
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

interface DefinitionBaseFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
  entities: CustomFieldEntity[]
  /** `entity_type`/`key`/`type` are immutable once the field has values; the edit form locks them regardless (server re-enforces on any attempted change). */
  lockIdentity: boolean
}

/**
 * "Base" group of a custom field DEFINITION: what the field IS — module, type,
 * key and label. The type-dependent sub-forms (config/options/relation/
 * validation) follow this section and precede "Presentation", so the form reads
 * top-to-bottom in the order an admin decides things. Every field carries an
 * always-visible inline helper.
 */
export function DefinitionBaseFields({ control, entities, lockIdentity }: DefinitionBaseFieldsProps) {
  const { t } = useTranslation()

  return (
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
        description={<FormDescription>{t('customFields.form.entityTypeHelp')}</FormDescription>}
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
        description={<FormDescription>{t('customFields.form.keyHelp')}</FormDescription>}
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
        description={<FormDescription>{t('customFields.form.labelHelp')}</FormDescription>}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
