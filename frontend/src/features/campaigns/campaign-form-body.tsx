import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FolderKanban, Megaphone, Tags } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/project-statuses/for-select-api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { useCampaignForm } from '@/features/campaigns/use-campaign-form'
import { CampaignProjectField } from '@/features/campaigns/campaign-project-field'
import { CampaignRelationField } from '@/features/campaigns/campaign-relation-field'
import { CampaignGeoSection } from '@/features/campaigns/campaign-geo-section'
import { CampaignPlanningSection } from '@/features/campaigns/campaign-planning-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'

interface CampaignFormBodyProps {
  mode: CampaignFormMode
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
}

/** Placeholder shown for the manual `code` field in create, declaring the server-generation fallback (spec 0025 AC-010). */
const CODE_PLACEHOLDER_KEY = 'campaigns.form.codePlaceholder'

/**
 * The campaign create/edit form UI: the manual/read-only `code` (spec 0025,
 * editable only in create — gated by the `code` field permission, editable
 * in create and read-only in update), identity (name, description), the
 * optional Project link driving the AC-042/AC-043 derivation, the always-own
 * relations, the 3 BR-2 classification fields (forced read-only while
 * linked), the geo cascade (BR-4/BR-5, spec 0027 — some levels forced
 * read-only while the linked project fills them) and planning/budget — all
 * wrapped in `MetaField` (spec 0004). All non-render logic lives in
 * `useCampaignForm`.
 */
export function CampaignFormBody({ mode, onSuccess, onCancel }: CampaignFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCampaignForm({ mode, onSuccess })
  const original = mode.type === 'edit' ? mode.campaign : null

  const projectId = useWatch({ control: form.control, name: 'project_id' })
  const isLinked = projectId !== null

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={Megaphone}
            title={t('campaigns.form.sections.identity.title')}
            description={t('campaigns.form.sections.identity.description')}
          >
            <MetaField control={form.control} name="code" metaKey="code" label={t('campaigns.form.code')}>
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

            <MetaField control={form.control} name="name" metaKey="name" label={t('campaigns.form.name')}>
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
              label={t('campaigns.form.description')}
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
            icon={FolderKanban}
            title={t('campaigns.form.sections.project.title')}
            description={t('campaigns.form.sections.project.description')}
          >
            <CampaignProjectField
              control={form.control}
              setValue={form.setValue}
              selected={original?.project ?? null}
            />

            <CampaignRelationField
              control={form.control}
              name="registry_id"
              metaKey="registry_id"
              label={t('campaigns.form.registry')}
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.registrySearch')}
              selected={original?.registry ?? null}
            />

            <CampaignRelationField
              control={form.control}
              name="source_id"
              metaKey="source_id"
              label={t('campaigns.form.source')}
              resource={SOURCES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.sourceSearch')}
              selected={original?.source ?? null}
            />

            <CampaignRelationField
              control={form.control}
              name="partner_id"
              metaKey="partner_id"
              label={t('campaigns.form.partner')}
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.partnerSearch')}
              selected={original?.partner ?? null}
            />
          </FormSection>

          <FormSection
            icon={Tags}
            title={t('campaigns.form.sections.classification.title')}
            description={t(
              isLinked
                ? 'campaigns.form.sections.classification.descriptionLinked'
                : 'campaigns.form.sections.classification.description',
            )}
          >
            <CampaignRelationField
              control={form.control}
              name="project_status_id"
              metaKey="project_status_id"
              label={t('campaigns.form.status')}
              resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.statusSearch')}
              selected={original?.project_status ?? null}
              forceDisabled={isLinked}
            />

            <CampaignRelationField
              control={form.control}
              name="business_function_id"
              metaKey="business_function_id"
              label={t('campaigns.form.businessFunction')}
              resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.businessFunctionSearch')}
              selected={original?.business_function ?? null}
              forceDisabled={isLinked}
            />

            <CampaignRelationField
              control={form.control}
              name="product_category_id"
              metaKey="product_category_id"
              label={t('campaigns.form.productCategory')}
              resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('campaigns.form.productCategorySearch')}
              selected={original?.product_category ?? null}
              forceDisabled={isLinked}
            />
          </FormSection>

          <CampaignGeoSection control={form.control} setValue={form.setValue} />

          <CampaignPlanningSection control={form.control} projectId={projectId} />

          <CustomFieldsSection resource="campaigns" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('campaigns.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('campaigns.form.saving') : t('campaigns.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
