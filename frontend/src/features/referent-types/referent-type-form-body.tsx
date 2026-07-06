import { Tag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useReferentTypeForm } from '@/features/referent-types/use-referent-type-form'
import type {
  ReferentTypeDetail,
  ReferentTypeFormMode,
} from '@/features/referent-types/types'

interface ReferentTypeFormBodyProps {
  mode: ReferentTypeFormMode
  onSuccess: (referentType: ReferentTypeDetail) => void
  onCancel: () => void
}

/**
 * The referent-type create/edit form UI. The single `name` field is wrapped
 * in `MetaField` (spec 0004): hidden means absent, non-editable means
 * disabled, `required` comes from the resolved `ResourcePermissions` — no
 * hardcoded permission logic lives here. All non-render logic lives in
 * `useReferentTypeForm`.
 */
export function ReferentTypeFormBody({ mode, onSuccess, onCancel }: ReferentTypeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useReferentTypeForm({ mode, onSuccess })

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
              icon={Tag}
              title={t('referentTypes.form.sections.identity.title')}
              description={t('referentTypes.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('referentTypes.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>
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
              {t('referentTypes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('referentTypes.form.saving')
                : t('referentTypes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
