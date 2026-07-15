import * as React from "react"
import { Progress as ProgressPrimitive } from "radix-ui"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const progressVariants = cva(
  "relative w-full overflow-hidden rounded-full bg-primary/20",
  {
    variants: {
      size: {
        xs: "h-1",
        sm: "h-1.5",
        default: "h-2",
      },
    },
    defaultVariants: {
      size: "sm",
    },
  }
)

interface ProgressProps
  extends React.ComponentProps<typeof ProgressPrimitive.Root>,
    VariantProps<typeof progressVariants> {
  indicatorClassName?: string
}

/** Compact progress bar. `value`/`max` drive the accessible `role="progressbar"` state (Radix). */
function Progress({
  className,
  indicatorClassName,
  size = "sm",
  value,
  max = 100,
  ...props
}: ProgressProps) {
  const percent = value == null ? 0 : Math.min(Math.max(value, 0), max)

  return (
    <ProgressPrimitive.Root
      data-slot="progress"
      value={value}
      max={max}
      className={cn(progressVariants({ size }), className)}
      {...props}
    >
      <ProgressPrimitive.Indicator
        data-slot="progress-indicator"
        className={cn("h-full w-full flex-1 rounded-full bg-primary transition-all", indicatorClassName)}
        style={{ transform: `translateX(-${100 - (percent / max) * 100}%)` }}
      />
    </ProgressPrimitive.Root>
  )
}

export { Progress, progressVariants }
export type { ProgressProps }
