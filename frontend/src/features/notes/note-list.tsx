import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { NoteComposer } from '@/features/notes/note-composer'
import { NoteItem } from '@/features/notes/note-item'
import type { Note } from '@/features/notes/types'

export interface NoteListProps {
  /** Root notes in server order (`created_at DESC`, D-13) — never re-sorted here. */
  roots: Note[]
  entityType: string
  entityId: number
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
}

/**
 * Renders the thread: each root, its replies indented one level (D-7 — no
 * recursive nesting), and a "load more" control for the next keyset page.
 * Owns which root currently shows its inline reply composer; edit-in-place is
 * owned by `NoteItem` itself.
 */
export function NoteList({ roots, entityType, entityId, hasNextPage, isFetchingNextPage, onLoadMore }: NoteListProps) {
  const { t } = useTranslation()
  const [replyingRootId, setReplyingRootId] = useState<number | null>(null)

  return (
    <div className="flex flex-col gap-4">
      {roots.map((root) => (
        <div key={root.id} className="flex flex-col gap-2">
          <NoteItem
            note={root}
            entityType={entityType}
            entityId={entityId}
            isRoot
            isReplying={replyingRootId === root.id}
            onToggleReply={() =>
              setReplyingRootId((current) => (current === root.id ? null : root.id))
            }
          />
          {(root.replies ?? []).length > 0 ? (
            <div className="ml-4 flex flex-col gap-2 border-l pl-3">
              {(root.replies ?? []).map((reply) => (
                <NoteItem
                  key={reply.id}
                  note={reply}
                  entityType={entityType}
                  entityId={entityId}
                  isRoot={false}
                  isReplying={false}
                />
              ))}
            </div>
          ) : null}
          {replyingRootId === root.id ? (
            <div className="ml-4 border-l pl-3">
              <NoteComposer
                entityType={entityType}
                entityId={entityId}
                parentId={root.id}
                onDone={() => setReplyingRootId(null)}
                onCancel={() => setReplyingRootId(null)}
                autoFocus
              />
            </div>
          ) : null}
        </div>
      ))}
      {hasNextPage ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onLoadMore}
          disabled={isFetchingNextPage}
          className="self-start"
        >
          {isFetchingNextPage ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
          {t('notes.list.loadMore', { defaultValue: 'Carica altre' })}
        </Button>
      ) : null}
    </div>
  )
}
