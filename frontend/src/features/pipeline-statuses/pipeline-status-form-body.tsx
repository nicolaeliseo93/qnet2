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
import { usePipelineStatusForm } from '@/features/pipeline-statuses/use-pipeline-status-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { PipelineStatusDetail, PipelineStatusFormMode } from '@/features/pipeline-statuses/types'

interface PipelineStatusFormBodyProps {
  mode: PipelineStatusFormMode
  onSuccess: (pipelineStatus: PipelineStatusDetail) => void
  onCancel: () => void
}

/**
 * The project status create/edit form UI. `name`, `color` and `status_group_id`
 * are each wrapped in `MetaField` (spec 0004): hidden means absent,
 * non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. A system
 * row ("Nuovo"/"Chiuso", spec 0039 D-2) forces the group picker disabled
 * regardless of field permissions: its group is fixed in migration and only
 * `name`/`color` are editable. `sort_order` has no form field (D-5,
 * server-managed). All non-render logic lives in `usePipelineStatusForm`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero pipeline-statuses-specific rendering/validation
 * logic.
 */
export function PipelineStatusFormBody({ mode, onSuccess, onCancel }: PipelineStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = usePipelineStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.pipelineStatus.system_key !== null

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
              title={t('pipelineStatuses.form.sections.identity.title')}
              description={t('pipelineStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('pipelineStatuses.form.name')}
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
                label={t('pipelineStatuses.form.color')}
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
                label={t('pipelineStatuses.form.statusGroup')}
                hint={isSystemRow ? t('pipelineStatuses.form.hints.systemStatusGroup') : undefined}
                resource={STATUS_GROUPS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('pipelineStatuses.form.statusGroupSearch')}
                selected={
                  mode.type === 'edit' && mode.pipelineStatus.status_group
                    ? { id: mode.pipelineStatus.status_group.id, name: mode.pipelineStatus.status_group.name }
                    : null
                }
                forceDisabled={isSystemRow}
                placeholder={t('pipelineStatuses.form.selectPlaceholder')}
                emptyLabel={t('pipelineStatuses.form.selectEmpty')}
                errorLabel={t('pipelineStatuses.form.selectError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />
            </FormSection>
          )}

          <CustomFieldsSection resource="pipeline-statuses" control={form.control} />

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
              {t('pipelineStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('pipelineStatuses.form.saving')
                : t('pipelineStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
