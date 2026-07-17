import { Percent } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useVatRateForm } from '@/features/vat-rates/use-vat-rate-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { VatRateDetail, VatRateFormMode } from '@/features/vat-rates/types'

interface VatRateFormBodyProps {
  mode: VatRateFormMode
  onSuccess: (vatRate: VatRateDetail) => void
  onCancel: () => void
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * The VAT rate create/edit form UI. `name` and `rate` are wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in `useVatRateForm`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero vat-rates-specific rendering/validation logic.
 */
export function VatRateFormBody({ mode, onSuccess, onCancel }: VatRateFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useVatRateForm({ mode, onSuccess })

  const identityVisible = fieldPermission('name').visible || fieldPermission('rate').visible

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
              icon={Percent}
              title={t('vatRates.form.sections.identity.title')}
              description={t('vatRates.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('vatRates.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="rate"
                metaKey="rate"
                label={t('vatRates.form.rate')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? null : Number(event.target.value))
                      }
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          <CustomFieldsSection resource="vat-rates" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline" className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('vatRates.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('vatRates.form.saving') : t('vatRates.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
