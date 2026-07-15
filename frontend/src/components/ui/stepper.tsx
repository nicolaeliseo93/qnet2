import * as React from "react"
import { Check, type LucideIcon } from "lucide-react"
import { cva } from "class-variance-authority"

import { cn } from "@/lib/utils"

type StepStatus = "completed" | "current" | "upcoming"

interface StepperStep {
  key: string
  label: string
  icon?: LucideIcon
  optional?: boolean
}

interface StepperProps extends Omit<React.ComponentProps<"nav">, "onClick"> {
  steps: StepperStep[]
  /** Zero-based index of the active step. */
  currentStep: number
  /** Fires only for reachable steps (index <= currentStep). */
  onStepClick?: (index: number) => void
}

const indicatorVariants = cva(
  "flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold transition-colors",
  {
    variants: {
      status: {
        completed: "border-primary bg-primary text-primary-foreground",
        current: "border-primary text-primary ring-2 ring-primary/20",
        upcoming: "border-muted-foreground/30 text-muted-foreground",
      } satisfies Record<StepStatus, string>,
    },
    defaultVariants: {
      status: "upcoming",
    },
  }
)

/** Derives step status from its position relative to the active step (no server state needed). */
function getStepStatus(index: number, currentStep: number): StepStatus {
  if (index < currentStep) return "completed"
  if (index === currentStep) return "current"
  return "upcoming"
}

/**
 * Compact horizontal step strip for multi-step wizards. Steps up to and
 * including the current one are "reachable" and clickable when
 * `onStepClick` is provided; future steps are inert. Status is conveyed by
 * icon + `aria-current`, never by color alone.
 */
function Stepper({ steps, currentStep, onStepClick, className, ...props }: StepperProps) {
  return (
    <nav aria-label="Progress" className={cn("w-full overflow-x-auto", className)} {...props}>
      <ol className="flex min-w-max items-center gap-1">
        {steps.map((step, index) => {
          const status = getStepStatus(index, currentStep)
          const reachable = status !== "upcoming"
          const clickable = reachable && Boolean(onStepClick)
          const Icon = step.icon

          return (
            <li key={step.key} className="flex items-center gap-1">
              {index > 0 ? (
                <span
                  aria-hidden="true"
                  className={cn(
                    "h-px w-4 shrink-0 sm:w-6",
                    index <= currentStep ? "bg-primary" : "bg-border"
                  )}
                />
              ) : null}
              <button
                type="button"
                disabled={!clickable}
                aria-current={status === "current" ? "step" : undefined}
                onClick={clickable ? () => onStepClick?.(index) : undefined}
                className={cn(
                  "flex items-center gap-1.5 rounded-md px-1.5 py-1 text-xs outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50",
                  clickable ? "cursor-pointer hover:bg-accent" : "cursor-default",
                  status === "upcoming" ? "text-muted-foreground" : "text-foreground",
                  status === "current" && "font-semibold"
                )}
              >
                <span className={cn(indicatorVariants({ status }))}>
                  {status === "completed" ? (
                    <Check className="size-3.5" aria-hidden="true" />
                  ) : Icon ? (
                    <Icon className="size-3.5" aria-hidden="true" />
                  ) : (
                    index + 1
                  )}
                </span>
                <span className="max-w-[100px] truncate sm:max-w-[160px]">
                  {step.label}
                  {step.optional ? (
                    <span className="ml-1 font-normal text-muted-foreground">(optional)</span>
                  ) : null}
                </span>
              </button>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}

export { Stepper, getStepStatus }
export type { StepperProps, StepperStep, StepStatus }
