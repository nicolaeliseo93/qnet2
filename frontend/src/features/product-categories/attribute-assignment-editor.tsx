import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useAttributeCatalog } from '@/features/attributes/use-attribute-catalog'
import { enumLabelOf } from '@/features/config/enum-label'
import type {
  AttributeAssignmentInput,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface AttributeAssignmentEditorProps {
  value: AttributeAssignmentInput[]
  onChange: (next: AttributeAssignmentInput[]) => void
  /** Read-only attributes inherited from the currently selected parent's ancestry chain. */
  inherited: ProductCategoryInheritedAttribute[]
  disabled?: boolean
}

/**
 * The category form's attribute-assignment editor (spec AC-022): picks an
 * attribute from the global catalog, then edits `is_required`/`sort_order`
 * per assigned row. Below it, a read-only list shows what this category
 * inherits from its ancestry — never editable here (inheritance is
 * recomputed server-side from the selected parent).
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
    <div className="flex flex-col gap-3">
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
                <Badge variant="secondary" className="text-xs">
                  {attribute ? enumLabelOf('attribute_type', attribute.data_type) : '—'}
                </Badge>
                <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Checkbox
                    checked={assignment.is_required}
                    disabled={disabled}
                    onCheckedChange={(checked) =>
                      updateAssignment(assignment.attribute_id, { is_required: checked === true })
                    }
                  />
                  {t('productCategories.form.isRequired')}
                </label>
                <Input
                  type="number"
                  className="w-16"
                  aria-label={t('productCategories.form.sortOrder')}
                  value={assignment.sort_order}
                  disabled={disabled}
                  onChange={(event) =>
                    updateAssignment(assignment.attribute_id, {
                      sort_order: Number(event.target.value) || 0,
                    })
                  }
                />
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
  )
}
