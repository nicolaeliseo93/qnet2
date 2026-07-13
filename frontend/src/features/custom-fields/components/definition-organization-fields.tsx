import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { LayoutGrid } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionOrganizationFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
}

/**
 * "Organization" group of a custom field DEFINITION: `group` and `sort_order`
 * — where it sits relative to other custom fields on the host form. Custom
 * fields only: an attribute has neither concept (no grid/tabs of its own;
 * `sort_order` for a category assignment lives on the pivot instead), so this
 * is NOT part of the shared `FieldDefinitionFormValues` sub-editors.
 */
export function DefinitionOrganizationFields({ control }: DefinitionOrganizationFieldsProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={LayoutGrid}
      title={t('customFields.form.sections.organization.title')}
      description={t('customFields.form.sections.organization.description')}
    >
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
