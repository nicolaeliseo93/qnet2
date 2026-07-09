import { useTranslation } from 'react-i18next'
import { Puzzle } from 'lucide-react'
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
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

interface CustomFieldDetailViewProps {
  definition: CustomFieldDefinitionDetail
}

/**
 * Read-only detail of a single custom field definition. Purely
 * presentational: the caller (the table's "view" sheet) fetches the fresh
 * detail and passes it down (mirrors `AttributeDetailView`). ENUM definitions
 * additionally list their options; RELATION definitions show their target.
 */
export function CustomFieldDetailView({ definition }: CustomFieldDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(definition.created_at)
  const sortedOptions = [...definition.options].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={definition.label} icon={<Puzzle />} />}
        title={definition.label}
        subtitle={definition.key}
      />

      <DetailSection title={t('customFields.detail.details')}>
        <DetailGrid>
          <DetailField label={t('customFields.columns.entity_type')}>
            <Badge variant="outline">{t(`customFields.entities.${definition.entity_type}`)}</Badge>
          </DetailField>
          <DetailField label={t('customFields.columns.type')}>
            <Badge variant="secondary">{t(`customFields.types.${definition.type}`)}</Badge>
          </DetailField>
          {definition.group ? (
            <DetailField label={t('customFields.columns.group')}>{definition.group}</DetailField>
          ) : null}
          <DetailField label={t('customFields.columns.is_active')}>
            {definition.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {definition.type === 'enum' && (
        <DetailSection title={t('customFields.detail.options')}>
          {sortedOptions.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('customFields.form.optionsEmpty')}</p>
          ) : (
            <ul className="flex flex-col gap-1.5">
              {sortedOptions.map((option) => (
                <li key={option.id} className="flex items-center gap-2 text-sm">
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

      {definition.type === 'relation' && definition.relation_target ? (
        <DetailSection title={t('customFields.detail.relation')}>
          <DetailGrid>
            <DetailField label={t('customFields.form.relationEntityType')}>
              {t(`customFields.entities.${definition.relation_target.entity_type}`)}
            </DetailField>
            <DetailField label={t('customFields.form.relationCardinality')}>
              {definition.relation_target.cardinality === 'many'
                ? t('customFields.form.relationCardinalityMany')
                : t('customFields.form.relationCardinalityOne')}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('customFields.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
