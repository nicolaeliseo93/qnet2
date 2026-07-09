import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ShieldCheck } from 'lucide-react'
import { FieldHint } from '@/components/field-hint'
import { FormSection } from '@/components/form-section'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel } from '@/components/ui/form'
import { HintLabel } from '@/features/custom-fields/components/definition-hint-label'
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
  /** Optional tooltip for the non-obvious rules (unique/exists/distinct/…). */
  hint?: string
}

function ValidationCheckboxField({ control, name, label, hint }: CheckboxFieldProps) {
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
          {hint ? <FieldHint text={hint} label={label} /> : null}
        </FormItem>
      )}
    />
  )
}

/**
 * Validation builder (spec AC-025): `required`/`unique` apply to every type;
 * `min`/`max` to numeric types; `regex`/`email`/`url` to text types;
 * `exists`/`distinct` to relation. Non-obvious rules carry a tooltip. Client-
 * side, this only feeds the write payload — the backend's
 * `FieldTypeHandler::validationRules()` remains the authoritative source of
 * truth (backend.md §8).
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
      <div className="flex flex-wrap gap-x-4 gap-y-3">
        <ValidationCheckboxField
          control={control}
          name="validation.required"
          label={t('customFields.form.validationRequired')}
          hint={t('customFields.form.validationRequiredHint')}
        />
        <ValidationCheckboxField
          control={control}
          name="validation.unique"
          label={t('customFields.form.validationUnique')}
          hint={t('customFields.form.validationUniqueHint')}
        />
        {isText && (
          <>
            <ValidationCheckboxField
              control={control}
              name="validation.email"
              label={t('customFields.form.validationEmail')}
              hint={t('customFields.form.validationEmailHint')}
            />
            <ValidationCheckboxField
              control={control}
              name="validation.url"
              label={t('customFields.form.validationUrl')}
              hint={t('customFields.form.validationUrlHint')}
            />
          </>
        )}
        {isRelation && (
          <>
            <ValidationCheckboxField
              control={control}
              name="validation.exists"
              label={t('customFields.form.validationExists')}
              hint={t('customFields.form.validationExistsHint')}
            />
            <ValidationCheckboxField
              control={control}
              name="validation.distinct"
              label={t('customFields.form.validationDistinct')}
              hint={t('customFields.form.validationDistinctHint')}
            />
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
                <HintLabel
                  hint={t('customFields.form.validationMinHint')}
                  hintLabel={t('customFields.form.validationMin')}
                >
                  {t('customFields.form.validationMin')}
                </HintLabel>
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
                <HintLabel
                  hint={t('customFields.form.validationMaxHint')}
                  hintLabel={t('customFields.form.validationMax')}
                >
                  {t('customFields.form.validationMax')}
                </HintLabel>
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
              <HintLabel
                hint={t('customFields.form.configRegexHint')}
                hintLabel={t('customFields.form.validationRegex')}
              >
                {t('customFields.form.validationRegex')}
              </HintLabel>
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
