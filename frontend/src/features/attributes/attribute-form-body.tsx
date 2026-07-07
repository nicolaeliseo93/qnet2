import { useFieldArray } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ListChecks, Plus, SlidersHorizontal, Trash2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useEnumOptions } from '@/features/config/use-config'
import { useAttributeForm } from '@/features/attributes/use-attribute-form'
import type { AttributeDataType, AttributeDetail, AttributeFormMode } from '@/features/attributes/types'

interface AttributeFormBodyProps {
  mode: AttributeFormMode
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * The attribute create/edit form UI. `code`/`name`/`data_type` are wrapped in
 * `MetaField` (spec 0004); the ENUM options editor is a plain `useFieldArray`
 * (not metadata-gated: it is part of the `data_type`/`options` field pair,
 * shown/hidden purely by the selected data type per AC-021). All non-render
 * logic lives in `useAttributeForm`.
 */
export function AttributeFormBody({ mode, onSuccess, onCancel }: AttributeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useAttributeForm({ mode, onSuccess })
  const dataTypeOptions = useEnumOptions('attribute_type')

  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'options' })
  const dataType = form.watch('data_type')
  const optionsVisible = dataType === 'ENUM'
  // The ENUM-requires-options / unique-values cross-field rule (superRefine)
  // attaches its issue to the `options` array itself, not to a sub-item.
  const optionsError = form.formState.errors.options?.message

  const identityVisible =
    fieldPermission('code').visible ||
    fieldPermission('name').visible ||
    fieldPermission('data_type').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={SlidersHorizontal}
              title={t('attributes.form.sections.identity.title')}
              description={t('attributes.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="code"
                metaKey="code"
                label={t('attributes.form.code')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('attributes.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="data_type"
                metaKey="data_type"
                label={t('attributes.form.dataType')}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value}
                    onValueChange={(next) => field.onChange(next as AttributeDataType)}
                    disabled={disabled}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {dataTypeOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>
            </FormSection>
          )}

          {optionsVisible && (
            <FormSection
              icon={ListChecks}
              title={t('attributes.form.sections.options.title')}
              description={t('attributes.form.sections.options.description')}
              aside={
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => append({ value: '', label: '' })}
                >
                  <Plus aria-hidden="true" />
                  {t('attributes.form.addOption')}
                </Button>
              }
            >
              {fields.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('attributes.form.optionsEmpty')}</p>
              ) : (
                fields.map((optionField, index) => (
                  <div key={optionField.id} className="flex items-start gap-2">
                    <FormField
                      control={form.control}
                      name={`options.${index}.value`}
                      render={({ field }) => (
                        <FormItem className="flex-1">
                          <FormLabel>{t('attributes.form.optionValue')}</FormLabel>
                          <FormControl>
                            <Input autoComplete="off" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name={`options.${index}.label`}
                      render={({ field }) => (
                        <FormItem className="flex-1">
                          <FormLabel>{t('attributes.form.optionLabel')}</FormLabel>
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
                      aria-label={t('attributes.form.removeOption')}
                      onClick={() => remove(index)}
                    >
                      <Trash2 aria-hidden="true" />
                    </Button>
                  </div>
                ))
              )}
              {optionsError ? (
                <p className="text-sm font-medium text-destructive" role="alert">
                  {optionsError}
                </p>
              ) : null}
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('attributes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('attributes.form.saving')
                : t('attributes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
