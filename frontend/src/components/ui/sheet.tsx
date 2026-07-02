import * as React from "react"
import { XIcon } from "lucide-react"
import { Dialog as SheetPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

function Sheet({ ...props }: React.ComponentProps<typeof SheetPrimitive.Root>) {
  return <SheetPrimitive.Root data-slot="sheet" {...props} />
}

function SheetTrigger({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Trigger>) {
  return <SheetPrimitive.Trigger data-slot="sheet-trigger" {...props} />
}

function SheetClose({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Close>) {
  return <SheetPrimitive.Close data-slot="sheet-close" {...props} />
}

function SheetPortal({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Portal>) {
  return <SheetPrimitive.Portal data-slot="sheet-portal" {...props} />
}

function SheetOverlay({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Overlay>) {
  return (
    <SheetPrimitive.Overlay
      data-slot="sheet-overlay"
      className={cn(
        "fixed inset-0 z-50 bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0",
        className
      )}
      {...props}
    />
  )
}

const SHEET_DEFAULT_WIDTH = 640
const SHEET_MIN_WIDTH = 380

function getSheetMaxWidth() {
  if (typeof window === "undefined") return SHEET_DEFAULT_WIDTH
  return Math.round(window.innerWidth * 0.95)
}

function clampSheetWidth(width: number) {
  return Math.min(Math.max(width, SHEET_MIN_WIDTH), getSheetMaxWidth())
}

function SheetContent({
  className,
  children,
  side = "right",
  showCloseButton = true,
  resizable = true,
  defaultWidth = SHEET_DEFAULT_WIDTH,
  storageKey,
  style,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Content> & {
  side?: "top" | "right" | "bottom" | "left"
  showCloseButton?: boolean
  /** Allow horizontal resizing by dragging the inner edge (left/right sheets only). */
  resizable?: boolean
  /** Default width in px, restored on double-click of the resize handle. */
  defaultWidth?: number
  /** localStorage key used to remember the chosen width. */
  storageKey?: string
}) {
  const isHorizontal = side === "left" || side === "right"
  const canResize = resizable && isHorizontal
  const resolvedKey = storageKey ?? `sheet-width-${side}`

  const [width, setWidth] = React.useState<number>(() => {
    if (!canResize || typeof window === "undefined") return defaultWidth
    const stored = window.localStorage.getItem(resolvedKey)
    const parsed = stored ? Number.parseInt(stored, 10) : NaN
    return Number.isFinite(parsed) ? clampSheetWidth(parsed) : defaultWidth
  })

  React.useEffect(() => {
    if (!canResize) return
    window.localStorage.setItem(resolvedKey, String(width))
  }, [canResize, resolvedKey, width])

  React.useEffect(() => {
    if (!canResize) return
    const onResize = () => setWidth((w) => clampSheetWidth(w))
    window.addEventListener("resize", onResize)
    return () => window.removeEventListener("resize", onResize)
  }, [canResize])

  const handleMouseDown = React.useCallback(
    (event: React.MouseEvent) => {
      event.preventDefault()
      document.body.style.userSelect = "none"
      document.body.style.cursor = "col-resize"

      const onMove = (e: MouseEvent) => {
        const next =
          side === "right"
            ? window.innerWidth - e.clientX
            : e.clientX
        setWidth(clampSheetWidth(next))
      }
      const onUp = () => {
        document.body.style.userSelect = ""
        document.body.style.cursor = ""
        window.removeEventListener("mousemove", onMove)
        window.removeEventListener("mouseup", onUp)
      }
      window.addEventListener("mousemove", onMove)
      window.addEventListener("mouseup", onUp)
    },
    [side]
  )

  const handleDoubleClick = React.useCallback(() => {
    setWidth(clampSheetWidth(defaultWidth))
  }, [defaultWidth])

  return (
    <SheetPortal>
      <SheetOverlay />
      <SheetPrimitive.Content
        data-slot="sheet-content"
        style={canResize ? { ...style, width } : style}
        className={cn(
          "fixed z-50 flex flex-col gap-4 bg-background shadow-lg transition ease-in-out data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:animate-in data-[state=open]:duration-500",
          side === "right" &&
            "inset-y-0 right-0 h-full border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right",
          side === "left" &&
            "inset-y-0 left-0 h-full border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left",
          isHorizontal && !canResize && "w-3/4 sm:max-w-sm",
          side === "top" &&
            "inset-x-0 top-0 h-auto border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top",
          side === "bottom" &&
            "inset-x-0 bottom-0 h-auto border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom",
          className
        )}
        {...props}
      >
        {canResize && (
          <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Resize panel"
            title="Drag to resize • double-click to reset"
            onMouseDown={handleMouseDown}
            onDoubleClick={handleDoubleClick}
            className={cn(
              "group/resize absolute inset-y-0 z-50 flex w-2 cursor-col-resize items-stretch justify-center select-none touch-none",
              side === "right" ? "left-0" : "right-0"
            )}
          >
            <div className="h-full w-px bg-border transition-colors group-hover/resize:bg-primary" />
          </div>
        )}
        {children}
        {showCloseButton && (
          <SheetPrimitive.Close className="absolute top-4 right-4 rounded-xs opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none data-[state=open]:bg-secondary">
            <XIcon className="size-4" />
            <span className="sr-only">Close</span>
          </SheetPrimitive.Close>
        )}
      </SheetPrimitive.Content>
    </SheetPortal>
  )
}

function SheetHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-header"
      className={cn("flex flex-col gap-1.5 p-4", className)}
      {...props}
    />
  )
}

function SheetFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-footer"
      className={cn("mt-auto flex flex-col gap-2 p-4", className)}
      {...props}
    />
  )
}

function SheetTitle({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Title>) {
  return (
    <SheetPrimitive.Title
      data-slot="sheet-title"
      className={cn("font-semibold text-foreground", className)}
      {...props}
    />
  )
}

function SheetDescription({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Description>) {
  return (
    <SheetPrimitive.Description
      data-slot="sheet-description"
      className={cn("text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

export {
  Sheet,
  SheetTrigger,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetFooter,
  SheetTitle,
  SheetDescription,
}
