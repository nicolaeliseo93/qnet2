import { Database } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useSourceForm } from '@/features/sources/use-source-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { SourceDetail, SourceFormMode } from '@/features/sources/types'

interface SourceFormBodyProps {
  mode: SourceFormMode
  onSuccess: (source: SourceDetail) => void
  onCancel: () => void
}

/**
 * The source create/edit form UI. The single `name` field is wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in `useSourceForm`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero sources-specific rendering/validation logic.
 */
export function SourceFormBody({ mode, onSuccess, onCancel }: SourceFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useSourceForm({ mode, onSuccess })

  const identityVisible = fieldPermission('name').visible

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
              icon={Database}
              title={t('sources.form.sections.identity.title')}
              description={t('sources.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('sources.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          <CustomFieldsSection resource="sources" control={form.control} />

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
              {t('sources.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('sources.form.saving') : t('sources.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
