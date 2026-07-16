import { Flag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { STATUS_GROUPS_FOR_SELECT_RESOURCE } from '@/features/status-groups/for-select-api'
import { useLeadStatusForm } from '@/features/lead-statuses/use-lead-status-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { LeadStatusDetail, LeadStatusFormMode } from '@/features/lead-statuses/types'

interface LeadStatusFormBodyProps {
  mode: LeadStatusFormMode
  onSuccess: (leadStatus: LeadStatusDetail) => void
  onCancel: () => void
}

/**
 * The lead status create/edit form UI. `name`, `color` and `status_group_id`
 * are each wrapped in `MetaField` (spec 0004): hidden means absent,
 * non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. A system
 * row ("Nuovo"/"Chiuso", spec 0039 D-2) forces the group picker disabled
 * regardless of field permissions: its group is fixed in migration and only
 * `name`/`color` are editable. `sort_order` has no form field (D-5,
 * server-managed). All non-render logic lives in `useLeadStatusForm`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero lead-statuses-specific rendering/validation logic.
 */
export function LeadStatusFormBody({ mode, onSuccess, onCancel }: LeadStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useLeadStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.leadStatus.system_key !== null

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('color').visible ||
    fieldPermission('status_group_id').visible

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
              icon={Flag}
              title={t('leadStatuses.form.sections.identity.title')}
              description={t('leadStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('leadStatuses.form.name')}
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
                label={t('leadStatuses.form.color')}
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

              <RelationSelectField
                control={form.control}
                name="status_group_id"
                metaKey="status_group_id"
                label={t('leadStatuses.form.statusGroup')}
                hint={isSystemRow ? t('leadStatuses.form.hints.systemStatusGroup') : undefined}
                resource={STATUS_GROUPS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leadStatuses.form.statusGroupSearch')}
                selected={
                  mode.type === 'edit' && mode.leadStatus.status_group
                    ? { id: mode.leadStatus.status_group.id, name: mode.leadStatus.status_group.name }
                    : null
                }
                forceDisabled={isSystemRow}
                placeholder={t('leadStatuses.form.selectPlaceholder')}
                emptyLabel={t('leadStatuses.form.selectEmpty')}
                errorLabel={t('leadStatuses.form.selectError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />
            </FormSection>
          )}

          <CustomFieldsSection resource="lead-statuses" control={form.control} />

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
              {t('leadStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('leadStatuses.form.saving')
                : t('leadStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
