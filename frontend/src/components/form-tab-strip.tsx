/**
 * Shared look for the app's form tab strips (user form, referent form): a
 * compact pill strip built on top of the design-system `components/ui/tabs`
 * base — same styling in every form so the tabbed layout reads consistently.
 * Presentation only; the forms own their tab items, visibility and content.
 */

/** Premium tab-strip container: bordered, tinted, compact. */
export const FORM_TAB_LIST_CLASS =
  'gap-1 rounded-lg border border-border/60 bg-muted/40 p-1 shadow-sm'

/** Premium tab trigger: pill, muted until active, icon nudges up when active. */
export const FORM_TAB_TRIGGER_CLASS =
  'gap-1.5 rounded-md px-2.5 py-1 text-xs text-muted-foreground transition-all duration-200 ' +
  'hover:bg-background/60 hover:text-foreground ' +
  'data-[state=active]:bg-background data-[state=active]:text-primary ' +
  'data-[state=active]:shadow-sm data-[state=active]:ring-1 data-[state=active]:ring-primary/15 ' +
  '[&_svg]:size-3.5 [&_svg]:transition-transform [&_svg]:duration-200 data-[state=active]:[&_svg]:scale-110'

/** Small dot marking a tab whose grouped sections carry a validation error. */
export function TabErrorDot({ label }: { label: string }) {
  return (
    <span
      className="size-1.5 shrink-0 rounded-full bg-destructive shadow-[0_0_0_2px] shadow-destructive/15"
      role="img"
      aria-label={label}
    />
  )
}
