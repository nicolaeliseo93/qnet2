import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TagsTable } from '@/features/tags/tags-table'

/**
 * Tags page. Light composition only: gates access with `tags.viewAny` and
 * mounts the thin Tags adapter, which in turn mounts the generic table
 * (`domain="tags"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function TagsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="tags.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('tags.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TagsTable />
      </div>
    </Can>
  )
}
