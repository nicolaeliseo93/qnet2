import { useTranslation } from 'react-i18next'
import { CalendarRange, FolderKanban, Tags } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/project-statuses/for-select-api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'
import { useProjectForm } from '@/features/projects/use-project-form'
import { ProjectRelationField } from '@/features/projects/project-relation-field'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'

interface ProjectFormBodyProps {
  mode: ProjectFormMode
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
}

/** Placeholder shown for the read-only, server-generated `code` field until the project is saved (AC-046). */
const CODE_PLACEHOLDER_KEY = 'projects.form.codePlaceholder'

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * The project create/edit form UI: the read-only `code` (AC-046), identity
 * (name, description), the required Stato relation (D-5) plus the 6 optional
 * relation pickers (`ProjectRelationField`), planning dates (BR-6) and
 * budget/target — all wrapped in `MetaField` (spec 0004). All non-render
 * logic lives in `useProjectForm`.
 */
export function ProjectFormBody({ mode, onSuccess, onCancel }: ProjectFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useProjectForm({ mode, onSuccess })
  const original = mode.type === 'edit' ? mode.project : null

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={FolderKanban}
            title={t('projects.form.sections.identity.title')}
            description={t('projects.form.sections.identity.description')}
          >
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium" htmlFor="project-code">
                {t('projects.form.code')}
              </label>
              <Input
                id="project-code"
                disabled
                readOnly
                value={original?.code ?? ''}
                placeholder={t(CODE_PLACEHOLDER_KEY)}
              />
            </div>

            <MetaField control={form.control} name="name" metaKey="name" label={t('projects.form.name')}>
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                </FormControl>
              )}
            </MetaField>

            <MetaField
              control={form.control}
              name="description"
              metaKey="description"
              label={t('projects.form.description')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Textarea
                    disabled={disabled}
                    readOnly={readOnly}
                    value={field.value ?? ''}
                    onChange={(event) => field.onChange(event.target.value || null)}
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                  />
                </FormControl>
              )}
            </MetaField>
          </FormSection>

          <FormSection
            icon={Tags}
            title={t('projects.form.sections.classification.title')}
            description={t('projects.form.sections.classification.description')}
          >
            <MetaField
              control={form.control}
              name="project_status_id"
              metaKey="project_status_id"
              label={t('projects.form.status')}
            >
              {({ field, disabled }) => (
                <FormControl>
                  <AsyncPaginatedSelect
                    resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
                    value={field.value}
                    onChange={field.onChange}
                    selectedItem={
                      original ? { id: original.project_status.id, label: original.project_status.name } : null
                    }
                    disabled={disabled}
                    labels={{
                      placeholder: t('projects.form.selectPlaceholder'),
                      searchPlaceholder: t('projects.form.statusSearch'),
                      empty: t('projects.form.selectEmpty'),
                      error: t('projects.form.selectError'),
                      clearLabel: t('common.clear'),
                      triggerLabel: t('projects.form.status'),
                      retry: t('common.retry'),
                    }}
                  />
                </FormControl>
              )}
            </MetaField>

            <ProjectRelationField
              control={form.control}
              name="registry_id"
              metaKey="registry_id"
              label={t('projects.form.registry')}
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.registrySearch')}
              selected={original?.registry ?? null}
            />

            <ProjectRelationField
              control={form.control}
              name="source_id"
              metaKey="source_id"
              label={t('projects.form.source')}
              resource={SOURCES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.sourceSearch')}
              selected={original?.source ?? null}
            />

            <ProjectRelationField
              control={form.control}
              name="business_function_id"
              metaKey="business_function_id"
              label={t('projects.form.businessFunction')}
              resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.businessFunctionSearch')}
              selected={original?.business_function ?? null}
            />

            <ProjectRelationField
              control={form.control}
              name="state_id"
              metaKey="state_id"
              label={t('projects.form.state')}
              resource={STATES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.stateSearch')}
              selected={original?.state ?? null}
            />

            <ProjectRelationField
              control={form.control}
              name="product_category_id"
              metaKey="product_category_id"
              label={t('projects.form.productCategory')}
              resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.productCategorySearch')}
              selected={original?.product_category ?? null}
            />

            <ProjectRelationField
              control={form.control}
              name="partner_id"
              metaKey="partner_id"
              label={t('projects.form.partner')}
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('projects.form.partnerSearch')}
              selected={original?.partner ?? null}
            />
          </FormSection>

          <FormSection
            icon={CalendarRange}
            title={t('projects.form.sections.planning.title')}
            description={t('projects.form.sections.planning.description')}
          >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <MetaField
                control={form.control}
                name="start_date"
                metaKey="start_date"
                label={t('projects.form.startDate')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="end_date"
                metaKey="end_date"
                label={t('projects.form.endDate')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <MetaField
                control={form.control}
                name="total_budget"
                metaKey="total_budget"
                label={t('projects.form.totalBudget')}
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

              <MetaField
                control={form.control}
                name="target_lead"
                metaKey="target_lead"
                label={t('projects.form.targetLead')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="1"
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
            </div>
          </FormSection>

          <CustomFieldsSection resource="projects" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('projects.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('projects.form.saving') : t('projects.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
