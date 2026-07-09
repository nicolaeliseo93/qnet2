import { useFieldArray, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ListChecks, Plus, Trash2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { IconPicker } from '@/components/icon-picker'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { blankCustomFieldOption } from '@/features/custom-fields/use-custom-field-definition-form'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionOptionsEditorProps {
  control: Control<CustomFieldDefinitionFormValues>
  /** The `options` array's own cross-field error (enum-requires-options / unique values), attached by `superRefine`. */
  optionsError?: string
}

/**
 * ENUM-only options editor (spec AC-025): add/remove rows of
 * value/label/color/icon/is_default. Shown/hidden purely by the selected
 * `type` (see `CustomFieldDefinitionFormBody`), not metadata-gated — it is
 * part of the `type`/`options` field pair, mirroring `AttributeFormBody`'s
 * ENUM options editor.
 */
export function DefinitionOptionsEditor({ control, optionsError }: DefinitionOptionsEditorProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: 'options' })

  return (
    <FormSection
      icon={ListChecks}
      title={t('customFields.form.sections.options.title')}
      description={t('customFields.form.sections.options.description')}
      aside={
        <Button type="button" variant="outline" size="sm" onClick={() => append(blankCustomFieldOption())}>
          <Plus aria-hidden="true" />
          {t('customFields.form.addOption')}
        </Button>
      }
    >
      {fields.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('customFields.form.optionsEmpty')}</p>
      ) : (
        fields.map((optionField, index) => (
          <div key={optionField.id} className="flex flex-col gap-2 rounded-lg border p-3">
            <div className="flex items-start gap-2">
              <FormField
                control={control}
                name={`options.${index}.value`}
                render={({ field }) => (
                  <FormItem className="flex-1">
                    <FormLabel>{t('customFields.form.optionValue')}</FormLabel>
                    <FormControl>
                      <Input autoComplete="off" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={control}
                name={`options.${index}.label`}
                render={({ field }) => (
                  <FormItem className="flex-1">
                    <FormLabel>{t('customFields.form.optionLabel')}</FormLabel>
                    <FormControl>
                      <Input autoComplete="off" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="mt-6 shrink-0"
                aria-label={t('customFields.form.removeOption')}
                onClick={() => remove(index)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
            <div className="flex items-end gap-2">
              <FormField
                control={control}
                name={`options.${index}.color`}
                render={({ field }) => (
                  <FormItem className="flex-1">
                    <FormLabel>{t('customFields.form.optionColor')}</FormLabel>
                    <FormControl>
                      <Input autoComplete="off" placeholder="blue" {...field} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={control}
                name={`options.${index}.icon`}
                render={({ field }) => (
                  <FormItem className="flex-1">
                    <FormLabel>{t('customFields.form.optionIcon')}</FormLabel>
                    <IconPicker
                      value={field.value}
                      onChange={field.onChange}
                      labels={{
                        placeholder: t('customFields.form.iconPickerPlaceholder'),
                        searchPlaceholder: t('customFields.form.iconSearchPlaceholder'),
                        empty: t('customFields.form.iconEmpty'),
                        clearLabel: t('customFields.form.iconClear'),
                      }}
                    />
                  </FormItem>
                )}
              />
              <FormField
                control={control}
                name={`options.${index}.is_default`}
                render={({ field }) => (
                  <FormItem className="flex items-center gap-2 pb-2">
                    <FormControl>
                      <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                    <FormLabel className="!mt-0">{t('customFields.form.optionIsDefault')}</FormLabel>
                  </FormItem>
                )}
              />
            </div>
          </div>
        ))
      )}
      {optionsError ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {optionsError}
        </p>
      ) : null}
    </FormSection>
  )
}

