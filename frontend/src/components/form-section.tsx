import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'

import { cn } from '@/lib/utils'

interface FormSectionProps {
  /** Contextual glyph rendered in the header chip (lucide icon component). */
  icon: LucideIcon
  title: ReactNode
  /** Short one-line description of what the section groups. */
  description?: ReactNode
  /** Right-aligned header slot (e.g. a count badge or a select-all control). */
  aside?: ReactNode
  children: ReactNode
  className?: string
}

/**
 * A titled card grouping related form fields: icon chip + title + description in
 * the header, a divider, then the fields. Gives complex forms a strong visual
 * hierarchy so each group is recognizable at a glance. Presentation only — it
 * carries no form state or authorization logic.
 */
export function FormSection({
  icon: Icon,
  title,
  description,
  aside,
  children,
  className,
}: FormSectionProps) {
  return (
    <section className={cn('rounded-xl border bg-card shadow-sm', className)}>
      <div className="flex items-center gap-3 px-4 py-3.5">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/15 bg-primary/10 text-primary">
          <Icon className="size-[18px]" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <h3 className="text-sm font-semibold tracking-tight text-foreground">
            {title}
          </h3>
          {description ? (
            <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
          ) : null}
        </div>
        {aside ? <div className="ml-auto flex items-center gap-2">{aside}</div> : null}
      </div>
      <div className="border-t" />
      <div className="flex flex-col gap-4 p-4">{children}</div>
    </section>
  )
}
