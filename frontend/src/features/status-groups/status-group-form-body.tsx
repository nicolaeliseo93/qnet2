import { Shapes } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { useStatusGroupForm } from '@/features/status-groups/use-status-group-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { StatusGroupDetail, StatusGroupFormMode } from '@/features/status-groups/types'

interface StatusGroupFormBodyProps {
  mode: StatusGroupFormMode
  onSuccess: (statusGroup: StatusGroupDetail) => void
  onCancel: () => void
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function sortOrderInputValue(value: number): string {
  return Number.isFinite(value) ? String(value) : ''
}

/**
 * The status group create/edit form UI. `name`, `color` and `sort_order` are
 * each wrapped in `MetaField` (spec 0004): hidden means absent, non-editable
 * means disabled, `required` comes from the resolved `ResourcePermissions`
 * — no hardcoded permission logic lives here. All non-render logic lives in
 * `useStatusGroupForm`. `<CustomFieldsSection>` (spec 0021) mounts the
 * resource's admin-defined custom fields with zero status-groups-specific
 * rendering/validation logic. Unlike the statuses themselves, `sort_order`
 * stays a manual numeric input (D-6: groups have no drag & drop reorder).
 */
export function StatusGroupFormBody({ mode, onSuccess, onCancel }: StatusGroupFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useStatusGroupForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('color').visible ||
    fieldPermission('sort_order').visible

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
              icon={Shapes}
              title={t('statusGroups.form.sections.identity.title')}
              description={t('statusGroups.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('statusGroups.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('statusGroups.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker
                      value={field.value}
                      onChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="sort_order"
                metaKey="sort_order"
                label={t('statusGroups.form.sortOrder')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="1"
                      min="0"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={sortOrderInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? 0 : Number(event.target.value))
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

          <CustomFieldsSection resource="status-groups" control={form.control} />

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
              {t('statusGroups.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('statusGroups.form.saving')
                : t('statusGroups.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
