import type { LucideIcon } from 'lucide-react'
import { Download, Eye, File, FileSpreadsheet, FileText, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { formatBytes } from '@/features/attachments/format-bytes'
import type { Attachment } from '@/features/attachments/types'

const SPREADSHEET_MIME_TYPES = new Set([
  'text/csv',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
])

type MimeCategory = 'spreadsheet' | 'document' | 'generic'

/** Non-image file-type icon, keyed by MIME category — no thumbnail exists for these. */
const CATEGORY_ICON: Record<MimeCategory, LucideIcon> = {
  spreadsheet: FileSpreadsheet,
  document: FileText,
  generic: File,
}

function mimeCategory(mimeType: string): MimeCategory {
  if (SPREADSHEET_MIME_TYPES.has(mimeType)) {
    return 'spreadsheet'
  }
  if (mimeType === 'application/pdf' || mimeType.startsWith('text/') || mimeType.startsWith('application/')) {
    return 'document'
  }
  return 'generic'
}

export interface AttachmentTileProps {
  attachment: Attachment
  canDelete: boolean
  onDelete: (attachment: Attachment) => void
}

/**
 * One document tile: an image thumbnail (via `view_url`) or a file-type icon,
 * the file name/size, and preview/download/delete affordances. Presentational
 * only — the parent owns the confirm + mutation flow behind `onDelete`.
 */
export function AttachmentTile({ attachment, canDelete, onDelete }: AttachmentTileProps) {
  const { t } = useTranslation()
  const isImage = attachment.mime_type.startsWith('image/')
  const Icon = CATEGORY_ICON[mimeCategory(attachment.mime_type)]

  return (
    <li className="flex flex-col gap-1.5 rounded-lg border bg-card p-2 text-xs shadow-sm">
      <div className="flex aspect-square items-center justify-center overflow-hidden rounded-md bg-muted">
        {isImage ? (
          <img
            src={attachment.view_url}
            alt={attachment.original_name}
            className="size-full object-cover"
          />
        ) : (
          <Icon className="size-6 text-muted-foreground" aria-hidden="true" />
        )}
      </div>
      <span className="truncate font-medium text-foreground" title={attachment.original_name}>
        {attachment.original_name}
      </span>
      <span className="text-muted-foreground">{formatBytes(attachment.size)}</span>
      <div className="flex items-center gap-0.5">
        <Button asChild variant="ghost" size="icon-xs" aria-label={t('attachments.preview')}>
          <a href={attachment.view_url} target="_blank" rel="noopener noreferrer">
            <Eye aria-hidden="true" />
          </a>
        </Button>
        <Button asChild variant="ghost" size="icon-xs" aria-label={t('attachments.download')}>
          <a href={attachment.download_url}>
            <Download aria-hidden="true" />
          </a>
        </Button>
        {canDelete ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('attachments.deleteAction')}
            onClick={() => onDelete(attachment)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        ) : null}
      </div>
    </li>
  )
}
