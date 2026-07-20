import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FolderKanban, Loader2, Tags } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { useProjectForm } from '@/features/projects/use-project-form'
import { ProjectGeographySection } from '@/features/projects/project-geography-section'
import { ProjectPlanningSection } from '@/features/projects/project-planning-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'

interface ProjectFormBodyProps {
  mode: ProjectFormMode
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
  /** Create-only: the sequential code suggestion prefilled into the `code` field (spec 0025). */
  initialCode?: string
}

/** Placeholder shown for the manual `code` field in create, declaring the server-generation fallback (spec 0025 AC-010). */
const CODE_PLACEHOLDER_KEY = 'projects.form.codePlaceholder'

/** Staggered mount reveal (motion-safe) for the two primary sections owned by this file; Geography/Planning carry their own. */
const SECTION_REVEAL_IDENTITY =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:fill-mode-both motion-safe:duration-300 motion-safe:delay-0'
const SECTION_REVEAL_CLASSIFICATION =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:fill-mode-both motion-safe:duration-300 motion-safe:delay-75'

/**
 * The project create/edit form UI: the manual/read-only `code` (spec 0025,
 * editable only in create — gated by the `code` field permission, editable
 * in create and read-only in update), identity (name, description), the
 * required Stato relation (D-5) plus the 5 optional relation pickers
 * (`ProjectRelationField`), the geo cascade (spec 0027 BR-4,
 * `ProjectGeographySection`), planning dates (BR-6) and budget/target
 * (`ProjectPlanningSection`) — all wrapped in `MetaField` (spec 0004). All
 * non-render logic lives in `useProjectForm`. Planning and custom fields are
 * secondary/optional groups, collapsed by default and force-opened on a
 * validation error so the message stays reachable.
 */
export function ProjectFormBody({ mode, onSuccess, onCancel, initialCode }: ProjectFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useProjectForm({ mode, onSuccess, initialCode })
  const original = mode.type === 'edit' ? mode.project : null

  const relationLabels = {
    placeholder: t('projects.form.selectPlaceholder'),
    emptyLabel: t('projects.form.selectEmpty'),
    errorLabel: t('projects.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  const [customOpen, setCustomOpen] = useState(false)
  const customHasError = Boolean(form.formState.errors.custom_fields)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={FolderKanban}
            title={t('projects.form.sections.identity.title')}
            description={t('projects.form.sections.identity.description')}
            className={SECTION_REVEAL_IDENTITY}
          >
            <MetaField
              control={form.control}
              name="code"
              metaKey="code"
              label={t('projects.form.code')}
              hint={t('projects.form.hints.code')}
              hintLabel={t('projects.form.code')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input
                    autoComplete="off"
                    disabled={disabled}
                    readOnly={readOnly}
                    placeholder={t(CODE_PLACEHOLDER_KEY)}
                    {...field}
                    value={field.value ?? ''}
                  />
                </FormControl>
              )}
            </MetaField>

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
            className={SECTION_REVEAL_CLASSIFICATION}
          >
            <div className="grid gap-3 sm:grid-cols-2">
              <RelationSelectField
                control={form.control}
                name="pipeline_status_id"
                metaKey="pipeline_status_id"
                label={t('projects.form.status')}
                resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('projects.form.statusSearch')}
                selected={original ? { id: original.pipeline_status.id, name: original.pipeline_status.name } : null}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="source_id"
                metaKey="source_id"
                label={t('projects.form.source')}
                resource={SOURCES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('projects.form.sourceSearch')}
                selected={original?.source ?? null}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="business_function_id"
                metaKey="business_function_id"
                label={t('projects.form.businessFunction')}
                resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('projects.form.businessFunctionSearch')}
                selected={original?.business_function ?? null}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="product_category_id"
                metaKey="product_category_id"
                label={t('projects.form.productCategory')}
                resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('projects.form.productCategorySearch')}
                selected={original?.product_category ?? null}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="partner_id"
                metaKey="partner_id"
                label={t('projects.form.partner')}
                hint={t('projects.form.hints.partner')}
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('projects.form.partnerSearch')}
                selected={original?.partner ?? null}
                {...relationLabels}
              />
            </div>
          </FormSection>

          <ProjectGeographySection control={form.control} setValue={form.setValue} />

          <ProjectPlanningSection control={form.control} />

          <CustomFieldsSection
            resource="projects"
            control={form.control}
            collapsible
            defaultOpen={false}
            open={customOpen || customHasError}
            onOpenChange={setCustomOpen}
          />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('projects.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting && <Loader2 className="mr-2 size-4 animate-spin" aria-hidden="true" />}
              {form.formState.isSubmitting ? t('projects.form.saving') : t('projects.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
