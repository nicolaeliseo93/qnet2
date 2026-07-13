import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { ProjectStatusDetail } from '@/features/project-statuses/types'

interface ProjectStatusDetailViewProps {
  projectStatus: ProjectStatusDetail
}

/**
 * Read-only detail of a single project status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `SourceDetailView`).
 */
export function ProjectStatusDetailView({ projectStatus }: ProjectStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(projectStatus.created_at)
  const swatch = swatchClassFor(projectStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={projectStatus.name} icon={<Flag />} />}
        title={projectStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('projectStatuses.detail.color')}>
            {projectStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${projectStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('projectStatuses.detail.sort_order')}>
            {projectStatus.sort_order}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('projectStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
