import { useFieldArray, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Database, Plus, Trash2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

interface ExtraFieldsEditorProps {
  control: Control<LeadFormValues>
}

/**
 * Free-form key/value pairs editor for `leads.extra_fields` (spec 0033):
 * add/remove rows, no fixed shape, no per-field permissions — mirrors
 * `DefinitionOptionsEditor`'s field-array pattern. Client-only UI state
 * (RHF field array); converted to/from the API record shape at the form
 * boundary (`recordToEntries`/`entriesToRecord`).
 */
export function ExtraFieldsEditor({ control }: ExtraFieldsEditorProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: 'extra_fields' })

  return (
    <FormSection
      icon={Database}
      title={t('leads.form.sections.extraFields.title')}
      description={t('leads.form.sections.extraFields.description')}
      aside={
        <Button type="button" variant="outline" size="sm" onClick={() => append({ key: '', value: '' })}>
          <Plus aria-hidden="true" />
          {t('leads.form.extraFields.add')}
        </Button>
      }
    >
      {fields.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('leads.form.extraFields.empty')}</p>
      ) : (
        fields.map((entryField, index) => (
          <div key={entryField.id} className="flex items-start gap-2">
            <FormField
              control={control}
              name={`extra_fields.${index}.key`}
              render={({ field }) => (
                <FormItem className="flex-1">
                  <FormLabel>{t('leads.form.extraFields.key')}</FormLabel>
                  <FormControl>
                    <Input
                      autoComplete="off"
                      placeholder={t('leads.form.extraFields.keyPlaceholder')}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={control}
              name={`extra_fields.${index}.value`}
              render={({ field }) => (
                <FormItem className="flex-1">
                  <FormLabel>{t('leads.form.extraFields.value')}</FormLabel>
                  <FormControl>
                    <Input
                      autoComplete="off"
                      placeholder={t('leads.form.extraFields.valuePlaceholder')}
                      {...field}
                    />
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
              aria-label={t('leads.form.extraFields.remove')}
              onClick={() => remove(index)}
            >
              <Trash2 aria-hidden="true" />
            </Button>
          </div>
        ))
      )}
    </FormSection>
  )
}
