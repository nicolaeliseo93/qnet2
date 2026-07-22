import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestRelationRef } from '@/features/request-management/types'

interface RequestAttributionSectionProps {
  control: Control<RequestWorkFormValues>
  /** The panel's hydrated `{id, name}` projections, for the pickers' labels. */
  source: RequestRelationRef | null
  reporter: RequestRelationRef | null
  operator: RequestRelationRef | null
}

/**
 * Where the request comes from and who owns it (user directive 2026-07-22):
 * "Fonte", "Segnalatore" and the GA2 "Operatore" — the same three dimensions
 * the opportunities form carries, made editable from the work panel too. Each
 * picker is the shared `RelationSelectField`, so its per-field gating comes
 * from the server-derived `permissions` block like every other field here.
 *
 * Changing the operator REASSIGNS the request: an actor without
 * `request-management.viewAll` loses access to it on the next read (D-3
 * scope), which is the intended semantics of handing a request over.
 */
export function RequestAttributionSection({
  control,
  source,
  reporter,
  operator,
}: RequestAttributionSectionProps) {
  const { t } = useTranslation()

  const selectLabels = {
    placeholder: t('requestManagement.workPanel.attribution.selectPlaceholder', {
      defaultValue: 'Select',
    }),
    emptyLabel: t('requestManagement.workPanel.attribution.selectEmpty', {
      defaultValue: 'No results',
    }),
    errorLabel: t('requestManagement.workPanel.attribution.selectError', {
      defaultValue: 'Could not load the options.',
    }),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Route}
      title={t('requestManagement.workPanel.attribution.title', { defaultValue: 'Attribution' })}
      description={t('requestManagement.workPanel.attribution.description', {
        defaultValue: 'Where the request comes from and who is working on it.',
      })}
    >
      <div className="grid gap-3 @2xl:grid-cols-2">
        <RelationSelectField
          control={control}
          name="source_id"
          metaKey="source_id"
          label={t('requestManagement.workPanel.attribution.source', { defaultValue: 'Source' })}
          resource={SOURCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('requestManagement.workPanel.attribution.sourceSearch', {
            defaultValue: 'Search a source',
          })}
          selected={source}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="reporter_id"
          metaKey="reporter_id"
          label={t('requestManagement.workPanel.attribution.reporter', { defaultValue: 'Reporter' })}
          resource={REFERENTS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('requestManagement.workPanel.attribution.reporterSearch', {
            defaultValue: 'Search a reporter',
          })}
          selected={reporter}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="operator_id"
          metaKey="operator_id"
          label={t('requestManagement.workPanel.attribution.operator', { defaultValue: 'Operator' })}
          resource={USERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('requestManagement.workPanel.attribution.operatorSearch', {
            defaultValue: 'Search an operator',
          })}
          selected={operator}
          showAvatar
          {...selectLabels}
        />
      </div>
    </FormSection>
  )
}
