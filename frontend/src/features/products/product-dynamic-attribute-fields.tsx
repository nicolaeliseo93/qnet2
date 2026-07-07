import { useEffect, useId, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { AttributeFieldValue } from '@/features/products/types'

interface ProductDynamicAttributeFieldsProps {
  categoryId: number | null
  value: Record<string, AttributeFieldValue>
  onChange: (next: Record<string, AttributeFieldValue>) => void
}

/** The default value seeded for a newly-appeared attribute, per data type. */
function defaultValueFor(dataType: EffectiveAttribute['data_type']): AttributeFieldValue {
  if (dataType === 'BOOLEAN') {
    return false
  }
  if (dataType === 'INTEGER' || dataType === 'DECIMAL') {
    return null
  }
  return ''
}

/** Rebuilds the attribute-value record from scratch: keeps values still relevant, seeds the rest. */
function mergeAttributeDefaults(
  effectiveAttributes: EffectiveAttribute[],
  previous: Record<string, AttributeFieldValue>,
): Record<string, AttributeFieldValue> {
  return Object.fromEntries(
    effectiveAttributes.map((attribute) => {
      const key = String(attribute.id)
      const existing = previous[key]
      return [key, existing !== undefined ? existing : defaultValueFor(attribute.data_type)]
    }),
  )
}

/**
 * Generates the product form's dynamic attribute fields from the selected
 * category's effective attributes (spec AC-023): STRING → text, INTEGER/
 * DECIMAL → number, BOOLEAN → checkbox, ENUM → select from options. Not
 * metadata-gated (spec: dynamic attributes are authorized at the
 * `products.update`/`products.create` resource level, not per field).
 */
export function ProductDynamicAttributeFields({
  categoryId,
  value,
  onChange,
}: ProductDynamicAttributeFieldsProps) {
  const { t } = useTranslation()
  const query = useEffectiveAttributes(categoryId)

  // Read via a ref so the seeding effect below only re-runs when the server
  // list itself changes (category switch), never on every keystroke that
  // updates `value`. Written from its OWN effect (not during render) per the
  // rules of refs.
  const valueRef = useRef(value)
  useEffect(() => {
    valueRef.current = value
  }, [value])

  useEffect(() => {
    if (!query.data) {
      return
    }
    onChange(mergeAttributeDefaults(query.data, valueRef.current))
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentionally re-seeds only when the loaded effective-attributes list changes; `value`/`onChange` are read via ref to avoid looping on every field edit
  }, [query.data])

  if (categoryId === null) {
    return null
  }

  if (query.isPending) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="flex flex-col items-start gap-2">
        <p className="text-sm text-destructive">{t('products.form.attributesLoadError')}</p>
        <Button variant="outline" size="sm" onClick={() => void query.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (query.data.length === 0) {
    return null
  }

  return (
    <div className="flex flex-col gap-3">
      {query.data.map((attribute) => (
        <DynamicAttributeField
          key={attribute.id}
          attribute={attribute}
          value={value[String(attribute.id)] ?? null}
          onChange={(next) => onChange({ ...value, [String(attribute.id)]: next })}
        />
      ))}
    </div>
  )
}

interface DynamicAttributeFieldProps {
  attribute: EffectiveAttribute
  value: AttributeFieldValue
  onChange: (next: AttributeFieldValue) => void
}

/** A single dynamic field, rendered per the attribute's `data_type`. */
function DynamicAttributeField({ attribute, value, onChange }: DynamicAttributeFieldProps) {
  const { t } = useTranslation()
  const fieldId = useId()
  const label = attribute.is_required ? `${attribute.name} *` : attribute.name

  if (attribute.data_type === 'BOOLEAN') {
    return (
      <label htmlFor={fieldId} className="flex items-center gap-2 text-sm">
        <Checkbox
          id={fieldId}
          checked={value === true}
          onCheckedChange={(checked) => onChange(checked === true)}
        />
        {label}
        {attribute.inherited && <InheritedBadge />}
      </label>
    )
  }

  if (attribute.data_type === 'ENUM') {
    return (
      <div className="flex flex-col gap-1.5">
        <Label htmlFor={fieldId} className="flex items-center gap-1.5">
          {label}
          {attribute.inherited && <InheritedBadge />}
        </Label>
        <Select value={typeof value === 'string' ? value : ''} onValueChange={onChange}>
          <SelectTrigger id={fieldId} className="w-full">
            <SelectValue placeholder={t('products.form.attributeSelectPlaceholder')} />
          </SelectTrigger>
          <SelectContent>
            {attribute.options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    )
  }

  const isNumeric = attribute.data_type === 'INTEGER' || attribute.data_type === 'DECIMAL'

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={fieldId} className="flex items-center gap-1.5">
        {label}
        {attribute.inherited && <InheritedBadge />}
      </Label>
      <Input
        id={fieldId}
        type={isNumeric ? 'number' : 'text'}
        step={attribute.data_type === 'INTEGER' ? 1 : 'any'}
        value={value === null ? '' : String(value)}
        onChange={(event) => {
          const raw = event.target.value
          if (!isNumeric) {
            onChange(raw)
            return
          }
          onChange(raw === '' ? null : Number(raw))
        }}
      />
    </div>
  )
}

/** Marks a field as inherited from an ancestor category (read/write, just informational). */
function InheritedBadge() {
  const { t } = useTranslation()
  return (
    <Badge variant="outline" className="text-[0.65rem] font-normal">
      {t('products.form.attributeInherited')}
    </Badge>
  )
}
