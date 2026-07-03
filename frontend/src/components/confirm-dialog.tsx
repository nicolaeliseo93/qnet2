import * as React from "react"
import { useTranslation } from "react-i18next"
import {
  CircleAlert,
  CircleCheck,
  CircleHelp,
  Info,
  TriangleAlert,
  type LucideIcon,
} from "lucide-react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import {
  ConfirmContext,
  type ConfirmFn,
  type ConfirmOptions,
  type ConfirmTone,
} from "@/components/confirm-dialog-context"

interface ToneStyle {
  icon: LucideIcon
  /** Solid badge behind the icon. */
  badge: string
  /** Icon color. */
  iconColor: string
  /** Pulsing halo color. */
  halo: string
  /** Confirm button variant classes. */
  action: string
}

const TONE_STYLES: Record<ConfirmTone, ToneStyle> = {
  default: {
    icon: CircleHelp,
    badge: "bg-primary/10",
    iconColor: "text-primary",
    halo: "bg-primary/25",
    action: buttonVariants(),
  },
  destructive: {
    icon: TriangleAlert,
    badge: "bg-destructive/10",
    iconColor: "text-destructive",
    halo: "bg-destructive/25",
    action: buttonVariants({ variant: "destructive" }),
  },
  success: {
    icon: CircleCheck,
    badge: "bg-emerald-500/10",
    iconColor: "text-emerald-600 dark:text-emerald-400",
    halo: "bg-emerald-500/25",
    action: buttonVariants(),
  },
  warning: {
    icon: CircleAlert,
    badge: "bg-amber-500/10",
    iconColor: "text-amber-600 dark:text-amber-400",
    halo: "bg-amber-500/25",
    action: buttonVariants(),
  },
  info: {
    icon: Info,
    badge: "bg-sky-500/10",
    iconColor: "text-sky-600 dark:text-sky-400",
    halo: "bg-sky-500/25",
    action: buttonVariants(),
  },
}

interface ConfirmState extends ConfirmOptions {
  open: boolean
}

/**
 * Mounts a single reusable confirmation dialog and exposes an imperative
 * `useConfirm()` that replaces `window.confirm`. Place once near the app root.
 */
export function ConfirmDialogProvider({
  children,
}: {
  children: React.ReactNode
}) {
  const { t } = useTranslation()
  const [state, setState] = React.useState<ConfirmState>({ open: false })
  const resolverRef = React.useRef<((result: boolean) => void) | null>(null)

  const confirm = React.useCallback<ConfirmFn>((options) => {
    return new Promise<boolean>((resolve) => {
      resolverRef.current = resolve
      setState({ ...options, open: true })
    })
  }, [])

  // Single exit point — resolve the pending promise, then close.
  const settle = React.useCallback((result: boolean) => {
    resolverRef.current?.(result)
    resolverRef.current = null
    setState((prev) => ({ ...prev, open: false }))
  }, [])

  const tone = TONE_STYLES[state.tone ?? "default"]
  const Icon = state.icon ?? tone.icon

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <AlertDialog
        open={state.open}
        onOpenChange={(next) => {
          // Any dismissal (Esc / cancel) resolves as declined.
          if (!next) settle(false)
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <span className="relative mx-auto flex size-12 items-center justify-center sm:mx-0">
              <span
                aria-hidden
                className={cn(
                  "absolute inline-flex size-full rounded-full opacity-75 motion-safe:animate-ping",
                  tone.halo
                )}
              />
              <span
                className={cn(
                  "relative inline-flex size-12 items-center justify-center rounded-full",
                  tone.badge
                )}
              >
                <Icon className={cn("size-6", tone.iconColor)} aria-hidden />
              </span>
            </span>
            <AlertDialogTitle>
              {state.title ?? t("common.confirmTitle")}
            </AlertDialogTitle>
            {state.description ? (
              <AlertDialogDescription>
                {state.description}
              </AlertDialogDescription>
            ) : null}
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>
              {state.cancelLabel ?? t("common.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              className={tone.action}
              onClick={() => settle(true)}
            >
              {state.confirmLabel ?? t("common.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </ConfirmContext.Provider>
  )
}
