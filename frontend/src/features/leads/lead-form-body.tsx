import { useTranslation } from 'react-i18next'
import { Contact, StickyNote } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { LEAD_STATUSES_FOR_SELECT_RESOURCE } from '@/features/lead-statuses/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useLeadForm } from '@/features/leads/use-lead-form'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

interface LeadFormBodyProps {
  mode: LeadFormMode
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * The lead create/edit form UI: the required Contact/Campaign/Lead-status
 * trio (BR-1, D-1) plus the 3 optional relations and the note — all wrapped
 * in `MetaField` (spec 0004). All non-render logic lives in `useLeadForm`.
 */
export function LeadFormBody({ mode, onSuccess, onCancel }: LeadFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useLeadForm({ mode, onSuccess })
  const original = mode.type === 'edit' ? mode.lead : null

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
          >
            <RelationSelectField
              control={form.control}
              name="referent_id"
              metaKey="referent_id"
              label={t('leads.form.referent')}
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.referentSearch')}
              selected={original?.referent ?? null}
              {...selectLabels}
            />

            <RelationSelectField
              control={form.control}
              name="campaign_id"
              metaKey="campaign_id"
              label={t('leads.form.campaign')}
              resource={CAMPAIGNS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.campaignSearch')}
              selected={original?.campaign ? { id: original.campaign.id, name: original.campaign.name } : null}
              {...selectLabels}
            />

            <RelationSelectField
              control={form.control}
              name="operational_site_id"
              metaKey="operational_site_id"
              label={t('leads.form.operationalSite')}
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
              resource={SOURCES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.sourceSearch')}
              selected={original?.source ?? null}
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
              {...selectLabels}
            />

            <RelationSelectField
              control={form.control}
              name="operator_id"
              metaKey="operator_id"
              label={t('leads.form.operator')}
              resource={USERS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.operatorSearch')}
              selected={original?.operator ?? null}
              {...selectLabels}
            />
          </FormSection>

          <FormSection
            icon={StickyNote}
            title={t('leads.form.sections.notes.title')}
            description={t('leads.form.sections.notes.description')}
          >
            <MetaField control={form.control} name="notes" metaKey="notes" label={t('leads.form.notes')}>
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

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('leads.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('leads.form.saving') : t('leads.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
