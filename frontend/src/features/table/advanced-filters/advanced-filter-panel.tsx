import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { ADVANCED_FILTER_FIELD_REGISTRY } from '@/features/table/advanced-filters/field-registry'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type {
  AdvancedFilterDescriptor,
  AdvancedFilterWidth,
} from '@/features/table/advanced-filters/types'

/**
 * Height transition applied to the `CollapsibleContent` that wraps the panel at
 * every call site, so opening and closing animate (spec 0032). Mirrors the
 * `ModuleStatsPanel` pattern; `motion-reduce` disables it for reduced-motion.
 */
export const ADVANCED_FILTER_PANEL_ANIMATION =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

/** Compact `max-w` token per declared width (ui-design.md §2: small elements by default). */
const WIDTH_CLASS: Record<AdvancedFilterWidth, string> = {
  sm: 'w-full sm:max-w-40',
  md: 'w-full sm:max-w-64',
  lg: 'w-full sm:max-w-80',
  full: 'w-full',
}

interface AdvancedFilterPanelProps {
  /** The domain's advanced filter catalog, already sorted by `order` (backend contract). */
  descriptors: AdvancedFilterDescriptor[]
  /** Draft/applied state and actions owned by `useAdvancedFilters`. */
  filters: UseAdvancedFiltersResult
}

/**
 * The generic, backend-driven advanced-filters panel (spec 0032): renders
 * every visible descriptor via the type -> field registry, ordered by
 * `order`, with an accessible required-error triad and a Reset/Applica
 * footer. Domain-agnostic: the same component serves all 21 domains, driven
 * entirely by the descriptor catalog `TableView` passes in.
 */
export function AdvancedFilterPanel({ descriptors, filters }: AdvancedFilterPanelProps) {
  const { t } = useTranslation()
  const panelId = useId()

  const visible = useMemo(
    () => descriptors.filter((descriptor) => descriptor.visible),
    [descriptors],
  )

  return (
    <div className="border-b border-border bg-muted/30">
      <div className="flex flex-wrap gap-3 p-3">
        {visible.map((descriptor) => {
          const fieldId = `${panelId}-${descriptor.name}`
          const errorId = `${fieldId}-error`
          const invalid = filters.isFieldInvalid(descriptor)
          const FieldComponent = ADVANCED_FILTER_FIELD_REGISTRY[descriptor.type]

          return (
            <div
              key={descriptor.name}
              className={cn('flex flex-col gap-1', WIDTH_CLASS[descriptor.width])}
            >
              <Label htmlFor={fieldId} className="text-xs font-medium text-muted-foreground">
                {t(descriptor.label)}
                {descriptor.required ? (
                  <span aria-hidden="true" className="text-destructive">
                    {' '}
                    *
                  </span>
                ) : null}
              </Label>
              <FieldComponent
                descriptor={descriptor}
                value={filters.draft[descriptor.name] ?? null}
                onChange={(value) => filters.setFieldValue(descriptor.name, value)}
                disabled={filters.isFieldDisabled(descriptor) || filters.isSaving}
                id={fieldId}
                describedBy={invalid ? errorId : undefined}
                invalid={invalid}
                dependencyParams={filters.dependencyParamsFor(descriptor)}
              />
              {invalid ? (
                <span id={errorId} role="alert" className="text-xs text-destructive">
                  {t('table.advancedFilters.requiredError')}
                </span>
              ) : null}
            </div>
          )
        })}
      </div>

      <div className="flex items-center justify-end gap-2 border-t border-border px-3 py-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={filters.reset}
          disabled={filters.isSaving}
        >
          {t('table.advancedFilters.reset')}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={filters.apply}
          disabled={!filters.canApply || filters.isSaving}
        >
          {t('table.advancedFilters.apply')}
        </Button>
      </div>
    </div>
  )
}
