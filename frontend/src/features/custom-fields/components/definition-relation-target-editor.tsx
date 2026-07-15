import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Waypoints } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { HintLabel } from '@/features/custom-fields/components/definition-hint-label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'

interface DefinitionRelationTargetEditorProps<T extends FieldDefinitionFormValues> {
  control: Control<T>
  entities: CustomFieldEntity[]
}

/**
 * RELATION-only `relation_target` editor (spec 0017/0021 AC-025), shared by
 * the custom field definition form AND the attribute form: target
 * entity_type, cardinality (one|many) and the for-select resource driving
 * `AsyncPaginatedSelect` on the host module's form. `for_select_resource`
 * reuses the same custom-fieldable entity catalogue as `entity_type` — in
 * this codebase a for-select resource always coincides with a registered
 * domain/resource key. Shown/hidden purely by the selected `type`.
 *
 * Generic over `T` (see `DefinitionTypePicker` for why `Control<T>` cannot be
 * fixed to `Control<FieldDefinitionFormValues>`).
 */
export function DefinitionRelationTargetEditor<T extends FieldDefinitionFormValues>({
  control,
  entities,
}: DefinitionRelationTargetEditorProps<T>) {
  const { t } = useTranslation()
  // See `DefinitionTypePicker`: `T` only ever adds fields on top of
  // `FieldDefinitionFormValues`, so every `relation_target.*` path here is
  // guaranteed present.
  const baseControl = control as unknown as Control<FieldDefinitionFormValues>

  return (
    <FormSection
      icon={Waypoints}
      title={t('customFields.form.sections.relation.title')}
      description={t('customFields.form.sections.relation.description')}
    >
      <FormField
        control={baseControl}
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
        control={baseControl}
        name="relation_target.cardinality"
        render={({ field }) => (
          <FormItem>
            <HintLabel
              hint={t('customFields.form.relationCardinalityHint')}
              hintLabel={t('customFields.form.relationCardinality')}
            >
              {t('customFields.form.relationCardinality')}
            </HintLabel>
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
        control={baseControl}
        name="relation_target.for_select_resource"
        render={({ field }) => (
          <FormItem>
            <HintLabel
              hint={t('customFields.form.relationForSelectResourceHint')}
              hintLabel={t('customFields.form.relationForSelectResource')}
            >
              {t('customFields.form.relationForSelectResource')}
            </HintLabel>
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
