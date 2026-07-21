import { useState, type ChangeEvent, type DragEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Upload } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useConfirm } from '@/components/confirm-dialog-context'
import { AttachmentTile } from '@/features/attachments/attachment-tile'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { useAttachments } from '@/features/attachments/use-attachments'
import type { Attachment } from '@/features/attachments/types'

export interface DocumentsSectionProps {
  /**
   * Polymorphic owner alias sent as `attachable_type` (e.g. `'opportunity'`,
   * per `config('attachments.attachable_types')`) — NOT the plural
   * table-registry resource key the activity log uses.
   */
  resource: string
  id: number
  collection?: string
  canUpload: boolean
  canDelete: boolean
}

const SKELETON_TILES = 4

/**
 * Self-contained document manager for a polymorphic owner: a compact
 * drag-and-drop upload strip, a grid of files (thumbnail/icon, preview,
 * download, delete) and its own loading/empty/error states. This component
 * owns no authorization gating of its own — the caller decides when it is
 * authorized to mount (mirrors `ActivityLogSection`).
 */
export function DocumentsSection({
  resource,
  id,
  collection = DOCUMENTS_COLLECTION,
  canUpload,
  canDelete,
}: DocumentsSectionProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { documents, isLoading, isError, refetch, upload, isUploading, remove } = useAttachments(
    resource,
    id,
    collection,
  )
  const [isDragging, setIsDragging] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const handleFile = (file: File | undefined) => {
    if (!file) {
      return
    }
    setActionError(null)
    upload(file).catch(() => setActionError(t('attachments.errors.upload')))
  }

  const handleInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    handleFile(event.target.files?.[0])
    // Reset so re-selecting the same file still fires a change event.
    event.target.value = ''
  }

  const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
    event.preventDefault()
    setIsDragging(false)
    handleFile(event.dataTransfer.files?.[0])
  }

  const handleDelete = async (attachment: Attachment) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('attachments.deleteAction'),
      description: t('attachments.deleteConfirm', { name: attachment.original_name }),
      confirmLabel: t('attachments.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    setActionError(null)
    try {
      await remove(attachment.id)
    } catch {
      setActionError(t('attachments.errors.delete'))
    }
  }

  return (
    <div className="flex flex-col gap-3">
      {canUpload ? (
        <label
          className={cn(
            'flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed px-3 py-3 text-xs text-muted-foreground transition-colors',
            'hover:border-primary/50 hover:bg-primary/5 focus-within:ring-[3px] focus-within:ring-ring/50',
            isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25',
          )}
          onDragOver={(event) => {
            event.preventDefault()
            setIsDragging(true)
          }}
          onDragLeave={(event) => {
            if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
              setIsDragging(false)
            }
          }}
          onDrop={handleDrop}
        >
          <input type="file" className="sr-only" onChange={handleInputChange} disabled={isUploading} />
          {isUploading ? (
            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
          ) : (
            <Upload className="size-3.5" aria-hidden="true" />
          )}
          <span>{isUploading ? t('attachments.uploading') : t('attachments.dropzoneHint')}</span>
        </label>
      ) : null}

      {actionError ? (
        <p role="alert" className="text-xs text-destructive">
          {actionError}
        </p>
      ) : null}

      {isLoading ? (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
          {Array.from({ length: SKELETON_TILES }).map((_, index) => (
            <Skeleton key={index} className="aspect-square w-full rounded-lg" />
          ))}
        </div>
      ) : isError ? (
        <div className="flex flex-col items-start gap-2">
          <p className="text-xs text-destructive">{t('attachments.errors.load')}</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : documents.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('attachments.empty')}</p>
      ) : (
        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
          {documents.map((document) => (
            <AttachmentTile key={document.id} attachment={document} canDelete={canDelete} onDelete={handleDelete} />
          ))}
        </ul>
      )}
    </div>
  )
}
