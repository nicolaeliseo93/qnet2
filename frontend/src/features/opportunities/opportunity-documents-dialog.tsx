import { useTranslation } from 'react-i18next'
import { FolderOpen } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'

export interface OpportunityDocumentsDialogProps {
  /** Opportunity id whose documents are shown; `null` closes the dialog. */
  opportunityId: number | null
  onOpenChange: (open: boolean) => void
}

/**
 * Row-action Dialog opened from the opportunities table's "documents" action:
 * mounts the shared `DocumentsSection` for a single opportunity, mirroring
 * `ResourceActivityDialog`'s row-action-Dialog shape. `resource` uses the
 * polymorphic singular alias (`'opportunity'`), not the plural table-registry
 * domain key.
 */
export function OpportunityDocumentsDialog({
  opportunityId,
  onOpenChange,
}: OpportunityDocumentsDialogProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  return (
    <Dialog open={opportunityId !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl gap-0 overflow-hidden p-0">
        <DialogHeader className="flex-row items-center gap-3 space-y-0 border-b bg-muted/40 px-5 py-4 text-left">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FolderOpen className="size-4.5" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <DialogTitle className="text-base">{t('attachments.title')}</DialogTitle>
            <DialogDescription className="text-xs">{t('attachments.dialogSubtitle')}</DialogDescription>
          </div>
        </DialogHeader>
        {opportunityId !== null ? (
          <div className="max-h-[70vh] overflow-y-auto px-5 py-4">
            <DocumentsSection
              resource="opportunity"
              id={opportunityId}
              canUpload={can('attachments.create')}
              canDelete={can('attachments.delete')}
            />
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
