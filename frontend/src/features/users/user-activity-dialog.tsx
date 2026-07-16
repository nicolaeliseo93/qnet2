import { useTranslation } from 'react-i18next'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { TableRow } from '@/features/table/types'

interface UserActivityDialogProps {
  /** Row whose activity log is shown; `null` closes the dialog. */
  row: TableRow | null
  onOpenChange: (open: boolean) => void
}

/**
 * Row-action Dialog opened from the users table (spec 0034, AC-015): mounts
 * the same reusable `ActivityLogSection` shown in the detail Sheet, so the
 * timeline rendering has a single source of truth.
 */
export function UserActivityDialog({ row, onOpenChange }: UserActivityDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={row !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('activityLog.title')}</DialogTitle>
        </DialogHeader>
        {row ? (
          <div className="max-h-[70vh] overflow-y-auto">
            <ActivityLogSection resource="users" id={row.id} />
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
