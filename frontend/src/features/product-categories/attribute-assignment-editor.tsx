import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Info, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { useAttributeCatalog } from '@/features/attributes/use-attribute-catalog'
import { enumLabelOf } from '@/features/config/enum-label'
import type {
  AttributeAssignmentInput,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'
import type { AttributeDataType } from '@/features/attributes/types'

interface AttributeAssignmentEditorProps {
  value: AttributeAssignmentInput[]
  onChange: (next: AttributeAssignmentInput[]) => void
  /** Read-only attributes inherited from the currently selected parent's ancestry chain. */
  inherited: ProductCategoryInheritedAttribute[]
  disabled?: boolean
}

interface InfoTooltipProps {
  /** Accessible name of the trigger AND the tooltip's own content (mirrors `AvatarWithTooltip`). */
  label: string
}

/**
 * A tiny, keyboard-reachable info affordance explaining a control next to it.
 * Module-scoped (not nested in a render function) so it keeps a stable
 * component identity across re-renders.
 */
function InfoTooltip({ label }: InfoTooltipProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          tabIndex={0}
          aria-label={label}
          className="inline-flex shrink-0 rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        >
          <Info className="size-3.5" aria-hidden="true" />
        </span>
      </TooltipTrigger>
      <TooltipContent side="top" variant="light" className="max-w-56">
        {label}
      </TooltipContent>
    </Tooltip>
  )
}

interface DataTypeBadgeProps {
  dataType: AttributeDataType
  description: string
}

/** The attribute's data-type badge, with a tooltip describing what that type means for the product form. */
function DataTypeBadge({ dataType, description }: DataTypeBadgeProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Badge variant="secondary" className="cursor-default text-xs" tabIndex={0}>
          {enumLabelOf('attribute_type', dataType)}
        </Badge>
      </TooltipTrigger>
      <TooltipContent side="top" variant="light" className="max-w-56">
        {description}
      </TooltipContent>
    </Tooltip>
  )
}

/**
 * The category form's attribute-assignment editor (spec AC-022): picks an
 * attribute from the global catalog, then edits `is_required`/`sort_order`
 * per assigned row. Below it, a read-only list shows what this category
 * inherits from its ancestry — never editable here (inheritance is
 * recomputed server-side from the selected parent). Every non-obvious control
 * (data type, required, order) carries a compact info tooltip so the row is
 * self-explanatory (task #19) without growing its height.
 */
export function AttributeAssignmentEditor({
  value,
  onChange,
  inherited,
  disabled,
}: AttributeAssignmentEditorProps) {
  const { t } = useTranslation()
  const [pickerValue, setPickerValue] = useState<number | null>(null)
  const catalogQuery = useAttributeCatalog()
  const catalog = useMemo(() => catalogQuery.data ?? [], [catalogQuery.data])

  const assignedIds = useMemo(() => new Set(value.map((a) => a.attribute_id)), [value])
  const pickerOptions = useMemo(
    () =>
      catalog
        .filter((attribute) => !assignedIds.has(attribute.id))
        .map((attribute) => ({ id: attribute.id, name: attribute.name })),
    [catalog, assignedIds],
  )
  const catalogById = useMemo(() => new Map(catalog.map((a) => [a.id, a])), [catalog])

  const addAssignment = (attributeId: number) => {
    onChange([...value, { attribute_id: attributeId, is_required: false, sort_order: value.length }])
    setPickerValue(null)
  }

  const updateAssignment = (attributeId: number, patch: Partial<AttributeAssignmentInput>) => {
    onChange(
      value.map((assignment) =>
        assignment.attribute_id === attributeId ? { ...assignment, ...patch } : assignment,
      ),
    )
  }

  const removeAssignment = (attributeId: number) => {
    onChange(value.filter((assignment) => assignment.attribute_id !== attributeId))
  }

  return (
    <TooltipProvider>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-muted-foreground">{t('productCategories.form.attributesHelp')}</p>

        {!disabled && (
          <SearchableSelect
            value={pickerValue}
            onChange={addAssignment}
            options={pickerOptions}
            isPending={catalogQuery.isPending}
            isError={catalogQuery.isError}
            onRetry={() => void catalogQuery.refetch()}
            labels={{
              placeholder: t('productCategories.form.addAttributePlaceholder'),
              searchPlaceholder: t('productCategories.form.attributeSearch'),
              empty: t('productCategories.form.attributeEmpty'),
              noMatch: t('productCategories.form.attributeNoMatch'),
              error: t('productCategories.form.attributeError'),
              retry: t('common.retry'),
            }}
          />
        )}

        {value.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t('productCategories.form.attributesEmpty')}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {value.map((assignment) => {
              const attribute = catalogById.get(assignment.attribute_id)
              return (
                <li
                  key={assignment.attribute_id}
                  className="flex items-center gap-2 rounded-md border px-2 py-1.5"
                >
                  <span className="min-w-0 flex-1 truncate text-sm">
                    {attribute?.name ?? `#${assignment.attribute_id}`}
                  </span>
                  {attribute ? (
                    <DataTypeBadge
                      dataType={attribute.data_type}
                      description={t(`productCategories.form.dataTypeDescription.${attribute.data_type}`)}
                    />
                  ) : (
                    <Badge variant="secondary" className="text-xs">
                      —
                    </Badge>
                  )}
                  <label className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Checkbox
                      checked={assignment.is_required}
                      disabled={disabled}
                      onCheckedChange={(checked) =>
                        updateAssignment(assignment.attribute_id, { is_required: checked === true })
                      }
                    />
                    {t('productCategories.form.isRequired')}
                    <InfoTooltip label={t('productCategories.form.isRequiredHelp')} />
                  </label>
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    <span>{t('productCategories.form.sortOrder')}</span>
                    <InfoTooltip label={t('productCategories.form.sortOrderHelp')} />
                    <Input
                      type="number"
                      className="w-14"
                      aria-label={t('productCategories.form.sortOrder')}
                      value={assignment.sort_order}
                      disabled={disabled}
                      onChange={(event) =>
                        updateAssignment(assignment.attribute_id, {
                          sort_order: Number(event.target.value) || 0,
                        })
                      }
                    />
                  </div>
                  {!disabled && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon-xs"
                      aria-label={t('productCategories.form.removeAttribute')}
                      onClick={() => removeAssignment(assignment.attribute_id)}
                    >
                      <Trash2 aria-hidden="true" />
                    </Button>
                  )}
                </li>
              )
            })}
          </ul>
        )}

        {inherited.length > 0 && (
          <div className="mt-2 flex flex-col gap-1.5 border-t pt-3">
            <p className="text-xs font-medium text-muted-foreground">
              {t('productCategories.form.inheritedAttributes')}
            </p>
            <ul className="flex flex-col gap-1.5">
              {inherited.map((attribute) => (
                <li
                  key={attribute.attribute_id}
                  className="flex items-center gap-2 rounded-md bg-muted/50 px-2 py-1.5 text-sm text-muted-foreground"
                >
                  <span className="min-w-0 flex-1 truncate">{attribute.name}</span>
                  <Badge variant="outline" className="text-xs">
                    {enumLabelOf('attribute_type', attribute.data_type)}
                  </Badge>
                  {attribute.is_required && (
                    <Badge variant="outline" className="text-xs">
                      {t('productCategories.form.isRequired')}
                    </Badge>
                  )}
                </li>
              ))}
            </ul>
          </div>
        )}

        {catalogQuery.isPending && value.length === 0 ? <Skeleton className="h-9 w-full" /> : null}
      </div>
    </TooltipProvider>
  )
}
