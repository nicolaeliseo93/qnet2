import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { cn } from "@/lib/utils"

interface StatCardProps {
  label: string
  value: React.ReactNode
  subtitle?: React.ReactNode
  icon?: React.ReactNode
  className?: string
}

/** Compact KPI tile (label, value, optional subtitle/icon). Composes `Card`. */
function StatCard({ label, value, subtitle, icon, className }: StatCardProps) {
  return (
    <Card className={cn("gap-1 py-3", className)}>
      <CardContent className="flex items-start justify-between gap-2 px-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="truncate text-xs font-medium text-muted-foreground">{label}</span>
          <span className="text-xl font-semibold tabular-nums">{value}</span>
          {subtitle ? (
            <span className="truncate text-xs text-muted-foreground">{subtitle}</span>
          ) : null}
        </div>
        {icon ? (
          <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground [&_svg]:size-4">
            {icon}
          </span>
        ) : null}
      </CardContent>
    </Card>
  )
}

export { StatCard }
