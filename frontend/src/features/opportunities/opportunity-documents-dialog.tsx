import { useTranslation } from 'react-i18next'
import {
  Dialog,
  DialogContent,
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
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('attachments.title')}</DialogTitle>
        </DialogHeader>
        {opportunityId !== null ? (
          <div className="max-h-[70vh] overflow-y-auto">
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
