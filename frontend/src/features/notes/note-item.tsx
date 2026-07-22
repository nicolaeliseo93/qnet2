import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { UserAvatar } from '@/components/user-avatar'
import { NoteBody } from '@/features/notes/note-body'
import { NoteComposer } from '@/features/notes/note-composer'
import { useDeleteNote } from '@/features/notes/use-note-mutations'
import type { Note } from '@/features/notes/types'

export interface NoteItemProps {
  note: Note
  entityType: string
  entityId: number
  /** True for a thread root; replies never show the "reply" action (D-7). */
  isRoot: boolean
  /** True while this root's inline reply composer is open. */
  isReplying: boolean
  /** Toggles the inline reply composer. Omitted for replies. */
  onToggleReply?: () => void
}

/**
 * One note row: avatar + author + timestamp, body with mention chips, and the
 * author-only edit/delete actions gated by `note.can` (AC-071, D-8). Editing
 * swaps the row for an inline `NoteComposer`; deleting asks for confirmation
 * via the app-wide `useConfirm` (requires `ConfirmDialogProvider`, already
 * mounted at the app root in `App.tsx`).
 */
export function NoteItem({ note, entityType, entityId, isRoot, isReplying, onToggleReply }: NoteItemProps) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const deleteNote = useDeleteNote(entityType, entityId)
  const [isEditing, setIsEditing] = useState(false)

  const handleDelete = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('notes.item.deleteAction', { defaultValue: 'Elimina nota' }),
      description: t('notes.item.deleteConfirm', {
        defaultValue: "La nota sparira' dall'elenco. Le eventuali risposte restano nascoste insieme ad essa.",
      }),
      confirmLabel: t('notes.item.deleteAction', { defaultValue: 'Elimina nota' }),
    })
    if (!confirmed) {
      return
    }
    await deleteNote.mutateAsync(note.id)
  }

  if (isEditing) {
    return (
      <NoteComposer
        entityType={entityType}
        entityId={entityId}
        editingNote={note}
        onDone={() => setIsEditing(false)}
        onCancel={() => setIsEditing(false)}
        autoFocus
      />
    )
  }

  return (
    <div className="flex gap-2">
      <UserAvatar name={note.author.name} src={note.author.avatar_url} size="sm" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
          <span className="text-sm font-medium text-foreground">{note.author.name}</span>
          <span className="text-xs text-muted-foreground">
            {formatDateTime(note.created_at, i18n.language)}
          </span>
          {note.edited_at ? (
            <span className="text-xs text-muted-foreground">
              {t('notes.item.edited', { defaultValue: '(modificato)' })}
            </span>
          ) : null}
        </div>
        <NoteBody body={note.body} />
        <div className="flex items-center gap-1">
          {isRoot && onToggleReply ? (
            <Button type="button" variant="ghost" size="xs" onClick={onToggleReply}>
              {isReplying ? t('common.cancel') : t('notes.item.replyAction', { defaultValue: 'Rispondi' })}
            </Button>
          ) : null}
          {note.can.update ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              aria-label={t('notes.item.editAction', { defaultValue: 'Modifica nota' })}
              onClick={() => setIsEditing(true)}
            >
              <Pencil aria-hidden="true" />
            </Button>
          ) : null}
          {note.can.delete ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              aria-label={t('notes.item.deleteAction', { defaultValue: 'Elimina nota' })}
              onClick={handleDelete}
              disabled={deleteNote.isPending}
            >
              {deleteNote.isPending ? (
                <Loader2 className="size-3 animate-spin" aria-hidden="true" />
              ) : (
                <Trash2 aria-hidden="true" />
              )}
            </Button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

/** Locale-aware date + time, mirroring `ActivityLogSection`'s formatter — no date library (constraint). */
function formatDateTime(value: string, language: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
