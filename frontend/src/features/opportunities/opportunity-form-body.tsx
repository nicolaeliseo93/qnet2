import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CircleAlert, Contact, Loader2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Form, FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { cn } from '@/lib/utils'
import { MetaField } from '@/features/authorization/MetaField'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OpportunityRegistryField } from '@/features/opportunities/opportunity-registry-field'
import { OpportunityClassificationSection } from '@/features/opportunities/opportunity-classification-section'
import { OpportunityTeamSection } from '@/features/opportunities/opportunity-team-section'
import { OpportunityPlanningSection } from '@/features/opportunities/opportunity-planning-section'
import { OpportunityFromLeadBanner } from '@/features/opportunities/opportunity-from-lead-banner'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import { OpportunityLeadField } from '@/features/opportunities/opportunity-lead-field'
import {
  NO_LEAD_SUBMISSION,
  useOpportunityForm,
  useOpportunityFormSubmit,
  type LeadSubmissionState,
} from '@/features/opportunities/use-opportunity-form'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import { useOpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'
import type { OpportunityDetail, OpportunityFormMode } from '@/features/opportunities/types'

interface OpportunityFormBodyProps {
  mode: OpportunityFormMode
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/** Motion-safe staggered entrance shared by every top-level section. */
const SECTION_REVEAL_CLASS =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300'

/** Staggers a section's entrance by 50ms per index via an arbitrary Tailwind property. */
function sectionRevealClassName(index: number): string {
  return cn(SECTION_REVEAL_CLASS, `[animation-delay:${index * 50}ms]`)
}

/**
 * The opportunity create/edit form UI (spec 0040 + amendment rev.1): an
 * optional in-form "Lead" picker in create (A-1) or its read-only equivalent
 * in edit (D-2), identity (name, the required anagrafica and its 3 BR-4
 * scoped relations), classification (company/site/function/category/source,
 * all 3 of company/company_site/operational_site mandatory per A-2), team
 * (supervisor + managers) and planning (dates/value/probability) — all
 * wrapped in `MetaField`. All non-render logic lives in `useOpportunityForm`/
 * `useOpportunityFormSubmit` and `useOpportunityLeadSelection`. BR-2 field
 * locking (from a linked Lead) applies uniformly whether the lead came from
 * the `?lead_id=N` deep-link or from picking one in the select
 * (`useOpportunityLeadSelection` unifies both).
 */
export function OpportunityFormBody({ mode, onSuccess, onCancel }: OpportunityFormBodyProps) {
  const { t } = useTranslation()

  const { form } = useOpportunityForm({ mode })

  // `useOpportunityLeadSelection` needs `form.setValue`, so it can only run
  // AFTER `useOpportunityForm` — and `leadSubmission` (below) can only be
  // computed after `leadSelection` exists. This ordering, not a ref, is what
  // keeps `useOpportunityFormSubmit`'s `onSubmit` un-stale (`react-hooks/refs`
  // disallows writing to a ref during render; this needs no ref at all).
  const initialLead = mode.type === 'create' ? mode.fromLead : undefined
  const leadSelection = useOpportunityLeadSelection(
    initialLead
      ? {
          leadId: initialLead.leadId,
          lockedFields: initialLead.lockedFields,
          referentName: initialLead.references.referent?.name ?? null,
        }
      : null,
    form.setValue,
  )

  const leadIsBlocked = leadSelection.state.existingOpportunityId !== null
  const leadSubmission: LeadSubmissionState =
    mode.type === 'create'
      ? {
          blocked: leadIsBlocked,
          fromLead:
            leadSelection.state.leadId !== null && !leadIsBlocked
              ? { leadId: leadSelection.state.leadId, lockedFields: leadSelection.state.lockedFields }
              : null,
        }
      : NO_LEAD_SUBMISSION

  const { serverError, onSubmit } = useOpportunityFormSubmit({ form, mode, leadSubmission, onSuccess })
  const selectedItems = useOpportunitySelectedItems(mode)

  // BR-2: the fields derived from a linked Lead are immutable — both when
  // editing an opportunity that already has one, and while creating one
  // (deep-link or in-form select, unified by `useOpportunityLeadSelection`).
  const lockedFields = new Set(
    mode.type === 'edit' ? mode.opportunity.locked_fields : leadSelection.state.lockedFields,
  )

  const { errors, isSubmitting } = form.formState
  const [planningOpen, setPlanningOpen] = useState(false)
  const planningHasError = Boolean(
    errors.start_date || errors.expected_close_date || errors.estimated_value || errors.success_probability,
  )

  const registryId = useWatch({ control: form.control, name: 'registry_id' })
  const registryChosen = registryId !== null

  // A-4: recap of the chosen person's primary contacts, under each of the 3
  // selects. commercial/reporter (A-3) are the whole platform list, independent
  // of the anagrafica; only the referent stays anagrafica-scoped (BR-4).
  const referentId = useWatch({ control: form.control, name: 'referent_id' })
  const commercialId = useWatch({ control: form.control, name: 'commercial_id' })
  const reporterId = useWatch({ control: form.control, name: 'reporter_id' })

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={Contact}
            title={t('opportunities.form.sections.identity.title')}
            description={t('opportunities.form.sections.identity.description')}
            className={sectionRevealClassName(0)}
          >
            {mode.type === 'create' ? (
              <OpportunityLeadField state={leadSelection.state} onSelect={leadSelection.selectLead} />
            ) : mode.opportunity.lead ? (
              <div className="grid gap-2">
                <Label>{t('opportunities.form.lead')}</Label>
                <Input value={mode.opportunity.lead.label} disabled readOnly />
              </div>
            ) : null}

            {mode.type === 'create' && leadSelection.state.leadId !== null && !leadIsBlocked ? (
              <OpportunityFromLeadBanner referentName={leadSelection.state.referentName} />
            ) : null}

            <MetaField control={form.control} name="name" metaKey="name" label={t('opportunities.form.name')}>
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                </FormControl>
              )}
            </MetaField>

            <OpportunityRegistryField
              control={form.control}
              setValue={form.setValue}
              selected={selectedItems.registry}
              forceDisabled={lockedFields.has('registry_id')}
            />

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="flex flex-col gap-1.5">
                <RelationSelectField
                  control={form.control}
                  name="referent_id"
                  metaKey="referent_id"
                  label={t('opportunities.form.referent')}
                  resource={REFERENTS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('opportunities.form.referentSearch')}
                  selected={selectedItems.referent}
                  params={registryId !== null ? { registry_id: registryId } : undefined}
                  forceDisabled={!registryChosen || lockedFields.has('referent_id')}
                  placeholder={t('opportunities.form.selectPlaceholder')}
                  emptyLabel={t('opportunities.form.selectEmpty')}
                  errorLabel={t('opportunities.form.selectError')}
                  clearLabel={t('common.clear')}
                  retryLabel={t('common.retry')}
                />
                <OpportunityContactRecap referentId={referentId} />
              </div>

              <div className="flex flex-col gap-1.5">
                <RelationSelectField
                  control={form.control}
                  name="commercial_id"
                  metaKey="commercial_id"
                  label={t('opportunities.form.commercial')}
                  resource={REFERENTS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('opportunities.form.commercialSearch')}
                  selected={selectedItems.commercial}
                  placeholder={t('opportunities.form.selectPlaceholder')}
                  emptyLabel={t('opportunities.form.selectEmpty')}
                  errorLabel={t('opportunities.form.selectError')}
                  clearLabel={t('common.clear')}
                  retryLabel={t('common.retry')}
                />
                <OpportunityContactRecap referentId={commercialId} />
              </div>

              <div className="flex flex-col gap-1.5">
                <RelationSelectField
                  control={form.control}
                  name="reporter_id"
                  metaKey="reporter_id"
                  label={t('opportunities.form.reporter')}
                  resource={REFERENTS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('opportunities.form.reporterSearch')}
                  selected={selectedItems.reporter}
                  placeholder={t('opportunities.form.selectPlaceholder')}
                  emptyLabel={t('opportunities.form.selectEmpty')}
                  errorLabel={t('opportunities.form.selectError')}
                  clearLabel={t('common.clear')}
                  retryLabel={t('common.retry')}
                />
                <OpportunityContactRecap referentId={reporterId} />
              </div>
            </div>
          </FormSection>

          <OpportunityClassificationSection
            control={form.control}
            setValue={form.setValue}
            getValues={form.getValues}
            selectedItems={selectedItems}
            lockedFields={lockedFields}
            className={sectionRevealClassName(1)}
          />

          <OpportunityTeamSection
            control={form.control}
            selectedItems={selectedItems}
            className={sectionRevealClassName(2)}
          />

          <OpportunityPlanningSection
            control={form.control}
            collapsible
            open={planningOpen || planningHasError}
            onOpenChange={setPlanningOpen}
            className={sectionRevealClassName(3)}
          />

          {serverError && (
            <div
              role="alert"
              className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
            >
              <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
              {serverError}
            </div>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
              {t('opportunities.form.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting || leadIsBlocked}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('opportunities.form.saving') : t('opportunities.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
