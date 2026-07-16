import { useTranslation } from 'react-i18next'
import { History, SlidersHorizontal } from 'lucide-react'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'

interface AttributeDetailViewProps {
  attribute: AttributeDetailWithPermissions
}

/**
 * Read-only detail of a single attribute. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`). ENUM attributes additionally list their
 * options (with color/icon), ordered by `sort_order`.
 */
export function AttributeDetailView({ attribute }: AttributeDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(attribute.created_at)
  const sortedOptions = [...attribute.options].sort((a, b) => a.sort_order - b.sort_order)
  const TypeIcon = FIELD_TYPE_ICONS[attribute.type]

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={attribute.name} icon={<SlidersHorizontal />} />}
        title={attribute.name}
        subtitle={attribute.code}
      />

      <DetailSection title={t('attributes.detail.details')}>
        <DetailGrid>
          <DetailField label={t('attributes.columns.type')}>
            <Badge variant="secondary" className="gap-1">
              <TypeIcon className="size-3.5" aria-hidden="true" />
              {t(`customFields.types.${attribute.type}`)}
            </Badge>
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {attribute.type === 'enum' && (
        <DetailSection title={t('attributes.detail.options')}>
          {sortedOptions.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('attributes.form.optionsEmpty')}</p>
          ) : (
            <ul className="flex flex-col gap-1.5">
              {sortedOptions.map((option) => (
                <li key={option.id} className="flex items-center gap-2 text-sm">
                  <DynamicIcon name={option.icon} className="size-3.5 text-muted-foreground" />
                  <Badge variant="outline" className="font-mono text-xs">
                    {option.value}
                  </Badge>
                  <span className="text-foreground">{option.label}</span>
                </li>
              ))}
            </ul>
          )}
        </DetailSection>
      )}

      {attribute.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="attributes" id={attribute.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('attributes.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
