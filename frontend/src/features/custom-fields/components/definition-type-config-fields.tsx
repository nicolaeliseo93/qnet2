import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Settings2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionTypeConfigFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
  type: CustomFieldType
}

/**
 * Types with no `config` options, so the config panel renders nothing:
 * `relation` (its target lives in a dedicated editor) and the string-backed
 * scalars (date/datetime/time/email/url/color), which are plain native inputs.
 */
const TYPES_WITHOUT_CONFIG: readonly CustomFieldType[] = [
  'relation',
  'date',
  'datetime',
  'time',
  'email',
  'url',
  'color',
]

/** Formats a nullable numeric config value for a controlled `<input type="number">` (mirrors `ProductFormBody`'s pattern). */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * Per-`type` config controls (spec AC-025): text→minLength/maxLength/regex/
 * transform; textarea→rows/maxLength; integer/decimal→min/max/step/decimals;
 * boolean→display(checkbox|switch); enum→display(select|multiselect|radio|
 * badge). Renders nothing for the config-less types (relation + the
 * string-backed scalars — see TYPES_WITHOUT_CONFIG). Not metadata-gated: it is
 * a per-type sub-form of the `type` field itself, mirroring the ENUM options
 * editor's status.
 */
export function DefinitionTypeConfigFields({ control, type }: DefinitionTypeConfigFieldsProps) {
  const { t } = useTranslation()

  if (TYPES_WITHOUT_CONFIG.includes(type)) {
    return null
  }

  return (
    <FormSection
      icon={Settings2}
      title={t('customFields.form.sections.config.title')}
      description={t('customFields.form.sections.config.description')}
    >
      {(type === 'text' || type === 'textarea') && (
        <FormField
          control={control}
          name="config.maxLength"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('customFields.form.configMaxLength')}</FormLabel>
              <FormControl>
                <Input
                  type="number"
                  min={0}
                  value={numberInputValue(field.value)}
                  onChange={(event) =>
                    field.onChange(event.target.value === '' ? null : Number(event.target.value))
                  }
                />
              </FormControl>
            </FormItem>
          )}
        />
      )}

      {type === 'text' && (
        <>
          <FormField
            control={control}
            name="config.minLength"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configMinLength')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={0}
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
            name="config.regex"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configRegex')}</FormLabel>
                <FormControl>
                  <Input autoComplete="off" {...field} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={control}
            name="config.transform"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configTransform')}</FormLabel>
                <Select value={field.value || 'none'} onValueChange={(next) => field.onChange(next === 'none' ? '' : next)}>
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="none">{t('customFields.form.configTransformNone')}</SelectItem>
                    <SelectItem value="upper">{t('customFields.form.configTransformUpper')}</SelectItem>
                    <SelectItem value="lower">{t('customFields.form.configTransformLower')}</SelectItem>
                    <SelectItem value="capitalize">{t('customFields.form.configTransformCapitalize')}</SelectItem>
                  </SelectContent>
                </Select>
              </FormItem>
            )}
          />
        </>
      )}

      {type === 'textarea' && (
        <FormField
          control={control}
          name="config.rows"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('customFields.form.configRows')}</FormLabel>
              <FormControl>
                <Input
                  type="number"
                  min={1}
                  value={numberInputValue(field.value)}
                  onChange={(event) =>
                    field.onChange(event.target.value === '' ? null : Number(event.target.value))
                  }
                />
              </FormControl>
            </FormItem>
          )}
        />
      )}

      {(type === 'integer' || type === 'decimal') && (
        <>
          <FormField
            control={control}
            name="config.min"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configMin')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    step={type === 'decimal' ? '0.01' : '1'}
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
            name="config.max"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configMax')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    step={type === 'decimal' ? '0.01' : '1'}
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
            name="config.step"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('customFields.form.configStep')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    step="0.01"
                    min={0}
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          {type === 'decimal' && (
            <FormField
              control={control}
              name="config.decimals"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('customFields.form.configDecimals')}</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min={0}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? null : Number(event.target.value))
                      }
                    />
                  </FormControl>
                </FormItem>
              )}
            />
          )}
        </>
      )}

      {type === 'boolean' && (
        <FormField
          control={control}
          name="config.display"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('customFields.form.configDisplay')}</FormLabel>
              <Select value={field.value || 'checkbox'} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="checkbox">{t('customFields.form.configDisplayCheckbox')}</SelectItem>
                  <SelectItem value="switch">{t('customFields.form.configDisplaySwitch')}</SelectItem>
                </SelectContent>
              </Select>
            </FormItem>
          )}
        />
      )}

      {type === 'enum' && (
        <FormField
          control={control}
          name="config.display"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('customFields.form.configDisplay')}</FormLabel>
              <Select value={field.value || 'select'} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="select">{t('customFields.form.configDisplaySelect')}</SelectItem>
                  <SelectItem value="multiselect">{t('customFields.form.configDisplayMultiselect')}</SelectItem>
                  <SelectItem value="radio">{t('customFields.form.configDisplayRadio')}</SelectItem>
                  <SelectItem value="badge">{t('customFields.form.configDisplayBadge')}</SelectItem>
                </SelectContent>
              </Select>
            </FormItem>
          )}
        />
      )}
    </FormSection>
  )
}
