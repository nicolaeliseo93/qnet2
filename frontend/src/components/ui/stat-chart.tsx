import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface StatChartPoint {
  label: string
  value: number
}

export interface StatChartProps {
  title: string
  points: StatChartPoint[]
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `points` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

/**
 * Charting library boundary: `recharts` is imported ONLY by `stat-chart-impl`,
 * which is code-split here so it never lands in the initial page chunk (AC-013).
 */
const StatChartImpl = React.lazy(() => import("@/components/ui/stat-chart-impl"))

/** Compact trend widget (lazy area chart). Composes `Card`. */
function StatChart({ title, points, formatValue, emptyLabel = "—", className }: StatChartProps) {
  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {points.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-40 w-full sm:h-48" />}>
            <StatChartImpl title={title} points={points} formatValue={formatValue} />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { StatChart }
