import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ShieldCheck } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel } from '@/components/ui/form'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionValidationEditorProps {
  control: Control<CustomFieldDefinitionFormValues>
  type: CustomFieldType
}

/** Formats a nullable numeric validation value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

interface CheckboxFieldProps {
  control: Control<CustomFieldDefinitionFormValues>
  name: 'validation.required' | 'validation.unique' | 'validation.email' | 'validation.url' | 'validation.exists' | 'validation.distinct'
  label: string
}

function ValidationCheckboxField({ control, name, label }: CheckboxFieldProps) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="flex items-center gap-2">
          <FormControl>
            <Checkbox checked={field.value} onCheckedChange={field.onChange} />
          </FormControl>
          <FormLabel className="!mt-0">{label}</FormLabel>
        </FormItem>
      )}
    />
  )
}

/**
 * Validation builder (spec AC-025): `required`/`unique` apply to every type;
 * `min`/`max` to numeric types; `regex`/`email`/`url` to text types;
 * `exists`/`distinct` to relation. Client-side, this only feeds the write
 * payload — the backend's `FieldTypeHandler::validationRules()` remains the
 * authoritative source of truth (backend.md §8).
 */
export function DefinitionValidationEditor({ control, type }: DefinitionValidationEditorProps) {
  const { t } = useTranslation()
  const isNumeric = type === 'integer' || type === 'decimal'
  const isText = type === 'text' || type === 'textarea'
  const isRelation = type === 'relation'

  return (
    <FormSection
      icon={ShieldCheck}
      title={t('customFields.form.sections.validation.title')}
      description={t('customFields.form.sections.validation.description')}
    >
      <div className="flex flex-wrap gap-4">
        <ValidationCheckboxField control={control} name="validation.required" label={t('customFields.form.validationRequired')} />
        <ValidationCheckboxField control={control} name="validation.unique" label={t('customFields.form.validationUnique')} />
        {isText && (
          <>
            <ValidationCheckboxField control={control} name="validation.email" label={t('customFields.form.validationEmail')} />
            <ValidationCheckboxField control={control} name="validation.url" label={t('customFields.form.validationUrl')} />
          </>
        )}
        {isRelation && (
          <>
            <ValidationCheckboxField control={control} name="validation.exists" label={t('customFields.form.validationExists')} />
            <ValidationCheckboxField control={control} name="validation.distinct" label={t('customFields.form.validationDistinct')} />
          </>
        )}
      </div>

      {isNumeric && (
        <div className="flex gap-3">
          <FormField
            control={control}
            name="validation.min"
            render={({ field }) => (
              <FormItem className="flex-1">
                <FormLabel>{t('customFields.form.validationMin')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={control}
            name="validation.max"
            render={({ field }) => (
              <FormItem className="flex-1">
                <FormLabel>{t('customFields.form.validationMax')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
        </div>
      )}

      {isText && (
        <FormField
          control={control}
          name="validation.regex"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('customFields.form.validationRegex')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
            </FormItem>
          )}
        />
      )}
    </FormSection>
  )
}
