import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Waypoints } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionRelationTargetEditorProps {
  control: Control<CustomFieldDefinitionFormValues>
  entities: CustomFieldEntity[]
}

/**
 * RELATION-only `relation_target` editor (spec AC-025): target entity_type,
 * cardinality (one|many) and the for-select resource driving
 * `AsyncPaginatedSelect` on the host module's form. `for_select_resource`
 * reuses the same custom-fieldable entity catalogue as `entity_type` — in
 * this codebase a for-select resource always coincides with a registered
 * domain/resource key. Shown/hidden purely by the selected `type`.
 */
export function DefinitionRelationTargetEditor({
  control,
  entities,
}: DefinitionRelationTargetEditorProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Waypoints}
      title={t('customFields.form.sections.relation.title')}
      description={t('customFields.form.sections.relation.description')}
    >
      <FormField
        control={control}
        name="relation_target.entity_type"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('customFields.form.relationEntityType')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('customFields.form.relationEntityTypePlaceholder')} />
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
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name="relation_target.cardinality"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('customFields.form.relationCardinality')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value="one">{t('customFields.form.relationCardinalityOne')}</SelectItem>
                <SelectItem value="many">{t('customFields.form.relationCardinalityMany')}</SelectItem>
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name="relation_target.for_select_resource"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('customFields.form.relationForSelectResource')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('customFields.form.relationForSelectResourcePlaceholder')} />
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
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}
