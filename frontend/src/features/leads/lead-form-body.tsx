import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CircleAlert, ClipboardList, Contact, Loader2, StickyNote } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { sectionRevealClassName } from '@/components/form-section-reveal'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { cn } from '@/lib/utils'
import { MetaField } from '@/features/authorization/MetaField'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { LEAD_STATUSES_FOR_SELECT_RESOURCE } from '@/features/lead-statuses/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useLeadForm } from '@/features/leads/use-lead-form'
import { NOTES_MAX_LENGTH } from '@/features/leads/lead-schema'
import { ExtraFieldsEditor } from '@/features/leads/extra-fields-editor'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

interface LeadFormBodyProps {
  mode: LeadFormMode
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * The lead create/edit form UI, grouped by prominence: the required
 * Contact/Campaign/Lead-status trio (BR-1, D-1) leads, the 3 optional
 * relations sit in a secondary "Details" section, and notes/extra fields
 * collapse out of the way (auto-reopening on validation errors) — all
 * wrapped in `MetaField` (spec 0004). All non-render logic lives in
 * `useLeadForm`.
 */
export function LeadFormBody({ mode, onSuccess, onCancel }: LeadFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useLeadForm({ mode, onSuccess })
  const original = mode.type === 'edit' ? mode.lead : null

  const { errors, isSubmitting } = form.formState
  const [notesOpen, setNotesOpen] = useState(true)
  const notesHasError = Boolean(errors.notes)
  const [extraOpen, setExtraOpen] = useState(true)
  const extraHasError = Boolean(errors.extra_fields)

  const notesValue = useWatch({ control: form.control, name: 'notes' })
  const notesLength = notesValue?.length ?? 0

  const selectLabels = {
    placeholder: t('leads.form.selectPlaceholder'),
    emptyLabel: t('leads.form.selectEmpty'),
    errorLabel: t('leads.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={Contact}
            title={t('leads.form.sections.contact.title')}
            description={t('leads.form.sections.contact.description')}
            className={sectionRevealClassName(0)}
          >
            <RelationSelectField
              control={form.control}
              name="referent_id"
              metaKey="referent_id"
              label={t('leads.form.referent')}
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.referentSearch')}
              selected={original?.referent ?? null}
              required
              showAvatar
              {...selectLabels}
            />

            <div className="grid gap-3 sm:grid-cols-2">
              <RelationSelectField
                control={form.control}
                name="campaign_id"
                metaKey="campaign_id"
                label={t('leads.form.campaign')}
                resource={CAMPAIGNS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.campaignSearch')}
                selected={
                  original?.campaign ? { id: original.campaign.id, name: original.campaign.name } : null
                }
                required
                {...selectLabels}
              />

              <RelationSelectField
                control={form.control}
                name="lead_status_id"
                metaKey="lead_status_id"
                label={t('leads.form.leadStatus')}
                resource={LEAD_STATUSES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.leadStatusSearch')}
                selected={original?.lead_status ?? null}
                required
                {...selectLabels}
              />
            </div>
          </FormSection>

          <FormSection
            icon={ClipboardList}
            title={t('leads.form.sections.details.title')}
            description={t('leads.form.sections.details.description')}
            className={sectionRevealClassName(1)}
          >
            <div className="grid gap-3 sm:grid-cols-2">
              <RelationSelectField
                control={form.control}
                name="operational_site_id"
                metaKey="operational_site_id"
                label={t('leads.form.operationalSite')}
                hint={t('leads.form.hints.operationalSite')}
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.operationalSiteSearch')}
                selected={
                  original?.operational_site
                    ? { id: original.operational_site.id, name: original.operational_site.label }
                    : null
                }
                {...selectLabels}
              />

              <RelationSelectField
                control={form.control}
                name="source_id"
                metaKey="source_id"
                label={t('leads.form.source')}
                hint={t('leads.form.hints.source')}
                resource={SOURCES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.sourceSearch')}
                selected={original?.source ?? null}
                {...selectLabels}
              />

              <RelationSelectField
                control={form.control}
                name="operator_id"
                metaKey="operator_id"
                label={t('leads.form.operator')}
                hint={t('leads.form.hints.operator')}
                resource={USERS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.operatorSearch')}
                selected={original?.operator ?? null}
                showAvatar
                {...selectLabels}
              />
            </div>
          </FormSection>

          <FormSection
            icon={StickyNote}
            title={t('leads.form.sections.notes.title')}
            description={t('leads.form.sections.notes.description')}
            className={sectionRevealClassName(2)}
            collapsible
            open={notesOpen || notesHasError}
            onOpenChange={setNotesOpen}
          >
            <MetaField control={form.control} name="notes" metaKey="notes" label={t('leads.form.notes')}>
              {({ field, disabled, readOnly }) => (
                <>
                  <FormControl>
                    <Textarea
                      className="min-h-24"
                      placeholder={t('leads.form.notesPlaceholder')}
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <span
                    aria-hidden="true"
                    className={cn(
                      'justify-self-end text-xs tabular-nums',
                      notesLength > NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
                    )}
                  >
                    {notesLength}/{NOTES_MAX_LENGTH}
                  </span>
                </>
              )}
            </MetaField>
          </FormSection>

          <ExtraFieldsEditor
            control={form.control}
            className={sectionRevealClassName(3)}
            collapsible
            open={extraOpen || extraHasError}
            onOpenChange={setExtraOpen}
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
              {t('leads.form.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('leads.form.saving') : t('leads.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
