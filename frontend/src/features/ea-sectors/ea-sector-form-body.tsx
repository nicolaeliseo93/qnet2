import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { ListTree } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useEaSectorTree } from '@/features/ea-sectors/use-ea-sector-tree'
import { collectSubtreeIds, flattenEaSectorTree } from '@/features/ea-sectors/flatten-tree'
import { useEaSectorForm } from '@/features/ea-sectors/use-ea-sector-form'
import type { EaSectorDetail, EaSectorFormMode } from '@/features/ea-sectors/types'

interface EaSectorFormBodyProps {
  mode: EaSectorFormMode
  onSuccess: (sector: EaSectorDetail) => void
  onCancel: () => void
}

/** Sentinel id representing "no parent" in the parent picker (no real sector has id 0). */
const ROOT_PARENT_VALUE = 0

/**
 * The sector create/edit form UI: `name` and `parent_id`, wrapped in
 * `MetaField` (spec 0004). All non-render logic lives in `useEaSectorForm`.
 */
export function EaSectorFormBody({ mode, onSuccess, onCancel }: EaSectorFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useEaSectorForm({ mode, onSuccess })
  const treeQuery = useEaSectorTree()

  const parentOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = mode.type === 'edit' ? collectSubtreeIds(nodes, mode.sector.id) : new Set<number>()
    return [
      { id: ROOT_PARENT_VALUE, name: t('eaSectors.form.noParent') },
      ...flattenEaSectorTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, mode, t])

  const identityVisible = fieldPermission('name').visible || fieldPermission('parent_id').visible

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
              icon={ListTree}
              title={t('eaSectors.form.sections.identity.title')}
              description={t('eaSectors.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('eaSectors.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="parent_id"
                metaKey="parent_id"
                label={t('eaSectors.form.parent')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <SearchableSelect
                      value={field.value ?? ROOT_PARENT_VALUE}
                      onChange={(next) =>
                        field.onChange(next === ROOT_PARENT_VALUE ? null : next)
                      }
                      options={parentOptions}
                      isPending={treeQuery.isPending}
                      isError={treeQuery.isError}
                      onRetry={() => void treeQuery.refetch()}
                      disabled={disabled}
                      labels={{
                        placeholder: t('eaSectors.form.parentPlaceholder'),
                        searchPlaceholder: t('eaSectors.form.parentSearch'),
                        empty: t('eaSectors.form.parentEmpty'),
                        noMatch: t('eaSectors.form.parentNoMatch'),
                        error: t('eaSectors.form.parentError'),
                        retry: t('common.retry'),
                      }}
                    />
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
              {t('eaSectors.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('eaSectors.form.saving') : t('eaSectors.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
