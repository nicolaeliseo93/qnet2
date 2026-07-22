import { Fragment } from 'react'
import { MentionBadge } from '@/features/notes/mention-badge'
import { splitIntoSegments } from '@/features/notes/mention-tokens'

export interface NoteBodyProps {
  /** Raw note body, with mention tokens still inline (D-12). */
  body: string
}

/**
 * Renders a note's raw body as safe React nodes: plain text runs plus
 * highlighted mention chips. Security-critical (react-security.md): never
 * `dangerouslySetInnerHTML` on this untrusted, user-authored text — the body
 * is split into segments and each one rendered as a plain text node or a
 * `<span>` chip, so React's own escaping applies throughout.
 */
export function NoteBody({ body }: NoteBodyProps) {
  const segments = splitIntoSegments(body)

  return (
    <p className="text-sm break-words whitespace-pre-wrap text-foreground">
      {segments.map((segment) =>
        segment.type === 'mention' && segment.userId !== undefined ? (
          <MentionBadge key={segment.key} userId={segment.userId} name={segment.content} />
        ) : (
          <Fragment key={segment.key}>{segment.content}</Fragment>
        ),
      )}
    </p>
  )
}
