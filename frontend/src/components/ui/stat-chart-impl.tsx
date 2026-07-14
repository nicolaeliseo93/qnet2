import * as React from "react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import type { StatChartPoint } from "@/components/ui/stat-chart"

/** Every theme color comes from a CSS variable so the chart follows dark mode. */
const SERIES_COLOR = "var(--chart-1)"
const AXIS_TICK = { fontSize: 10, fill: "var(--muted-foreground)" } as const
const TOOLTIP_CONTENT_STYLE: React.CSSProperties = {
  backgroundColor: "var(--popover)",
  border: "1px solid var(--border)",
  borderRadius: "var(--radius-md)",
  color: "var(--popover-foreground)",
  fontSize: "0.75rem",
  padding: "0.25rem 0.5rem",
}
const CHART_MARGIN = { top: 4, right: 4, bottom: 0, left: 0 } as const

interface StatChartImplProps {
  title: string
  points: StatChartPoint[]
  formatValue?: (value: number) => string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/** Recharts-backed area chart. Loaded lazily by `StatChart` — do not import it eagerly. */
export default function StatChartImpl({
  title,
  points,
  formatValue = defaultFormatValue,
}: StatChartImplProps) {
  const gradientId = `${React.useId()}-fill`

  return (
    <figure className="m-0 flex flex-col">
      {/* The chart itself is decorative for assistive tech: the data is exposed as text below. */}
      <div aria-hidden className="h-40 w-full sm:h-48">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={points} margin={CHART_MARGIN}>
            <defs>
              <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={SERIES_COLOR} stopOpacity={0.35} />
                <stop offset="100%" stopColor={SERIES_COLOR} stopOpacity={0.02} />
              </linearGradient>
            </defs>
            <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
            <XAxis
              dataKey="label"
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={false}
              interval="preserveStartEnd"
              minTickGap={12}
            />
            <YAxis
              width={36}
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={false}
              allowDecimals={false}
              tickFormatter={(value: number) => formatValue(value)}
            />
            <Tooltip
              cursor={{ stroke: "var(--border)" }}
              contentStyle={TOOLTIP_CONTENT_STYLE}
              labelStyle={{ color: "var(--muted-foreground)" }}
              itemStyle={{ color: "var(--popover-foreground)" }}
              formatter={(value) => formatValue(Number(value))}
            />
            <Area
              type="monotone"
              dataKey="value"
              stroke={SERIES_COLOR}
              strokeWidth={2}
              fill={`url(#${gradientId})`}
              isAnimationActive={false}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      <ul className="sr-only">
        {points.map((point) => (
          <li key={point.label}>{`${point.label}: ${formatValue(point.value)}`}</li>
        ))}
      </ul>
    </figure>
  )
}
