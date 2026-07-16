import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { ChevronDown } from 'lucide-react'

import { cn } from '@/lib/utils'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'

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
  /** When true, the header becomes a toggle collapsing the body (default: false, current behaviour). */
  collapsible?: boolean
  /** Uncontrolled initial open state, only used when `open` is not provided. */
  defaultOpen?: boolean
  /** Controlled open state. Provide together with `onOpenChange` to control the section. */
  open?: boolean
  onOpenChange?: (open: boolean) => void
}

/**
 * A titled card grouping related form fields: icon chip + title + description in
 * the header, a divider, then the fields. Gives complex forms a strong visual
 * hierarchy so each group is recognizable at a glance. Presentation only — it
 * carries no form state or authorization logic.
 *
 * Optionally `collapsible`: the header becomes a `Collapsible` trigger with a
 * rotating chevron, body height-animated via `tw-animate-css` (motion-safe).
 */
export function FormSection({
  icon: Icon,
  title,
  description,
  aside,
  children,
  className,
  collapsible = false,
  defaultOpen = true,
  open,
  onOpenChange,
}: FormSectionProps) {
  const header = (
    <>
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
    </>
  )

  if (!collapsible) {
    return (
      <section className={cn('rounded-xl border bg-card shadow-sm', className)}>
        <div className="flex items-center gap-3 px-4 py-3.5">
          {header}
          {aside ? <div className="ml-auto flex items-center gap-2">{aside}</div> : null}
        </div>
        <div className="border-t" />
        <div className="flex flex-col gap-4 p-4">{children}</div>
      </section>
    )
  }

  return (
    <Collapsible
      open={open}
      defaultOpen={defaultOpen}
      onOpenChange={onOpenChange}
      asChild
    >
      <section className={cn('rounded-xl border bg-card shadow-sm', className)}>
        <CollapsibleTrigger asChild>
          <button
            type="button"
            className="group flex w-full items-center gap-3 rounded-t-xl px-4 py-3.5 text-left transition-colors hover:bg-muted/40 outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50"
          >
            {header}
            {aside ? (
              <span
                className="ml-auto flex items-center gap-2"
                onClick={(event) => event.stopPropagation()}
              >
                {aside}
              </span>
            ) : null}
            <ChevronDown
              className="size-4 shrink-0 text-muted-foreground transition-transform motion-safe:duration-200 group-data-[state=open]:rotate-180"
              aria-hidden="true"
            />
          </button>
        </CollapsibleTrigger>
        <CollapsibleContent className="form-section-collapsible-content overflow-hidden">
          <div className="border-t" />
          <div className="flex flex-col gap-4 p-4">{children}</div>
        </CollapsibleContent>
      </section>
    </Collapsible>
  )
}
