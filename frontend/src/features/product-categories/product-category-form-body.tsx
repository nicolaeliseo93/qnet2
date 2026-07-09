import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { FolderTree, ListChecks } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormDescription } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import { collectSubtreeIds, flattenCategoryTree } from '@/features/product-categories/flatten-tree'
import { useProductCategoryForm } from '@/features/product-categories/use-product-category-form'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface ProductCategoryFormBodyProps {
  mode: ProductCategoryFormMode
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/** Sentinel id representing "no parent" in the parent picker (no real category has id 0). */
const ROOT_PARENT_VALUE = 0

/**
 * The category create/edit form UI: identity fields (name, parent,
 * description) wrapped in `MetaField` (spec 0004), followed by the
 * attribute-assignment editor (own assignments + read-only inherited list).
 * All non-render logic lives in `useProductCategoryForm`.
 */
export function ProductCategoryFormBody({ mode, onSuccess, onCancel }: ProductCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useProductCategoryForm({ mode, onSuccess })
  const treeQuery = useProductCategoryTree()

  const parentId = form.watch('parent_id')
  const inheritsAttributes = form.watch('inherits_attributes')
  const inheritedQuery = useEffectiveAttributes(parentId)
  // Opting out is a barrier: the category inherits nothing, so the read-only
  // inherited list must reflect that immediately (not just after save).
  const inherited: ProductCategoryInheritedAttribute[] = useMemo(
    () =>
      inheritsAttributes
        ? (inheritedQuery.data ?? []).map((attribute) => ({
            attribute_id: attribute.id,
            code: attribute.code,
            name: attribute.name,
            data_type: attribute.data_type,
            is_required: attribute.is_required,
          }))
        : [],
    [inheritedQuery.data, inheritsAttributes],
  )

  const parentOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = mode.type === 'edit' ? collectSubtreeIds(nodes, mode.category.id) : new Set<number>()
    return [
      { id: ROOT_PARENT_VALUE, name: t('productCategories.form.noParent') },
      ...flattenCategoryTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, mode, t])

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('parent_id').visible ||
    fieldPermission('description').visible
  const attributesVisible = fieldPermission('attributes').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={FolderTree}
              title={t('productCategories.form.sections.identity.title')}
              description={t('productCategories.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('productCategories.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="parent_id"
                metaKey="parent_id"
                label={t('productCategories.form.parent')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <SearchableSelect
                      value={field.value ?? ROOT_PARENT_VALUE}
                      onChange={(next) =>
                        field.onChange(next === ROOT_PARENT_VALUE ? null : next)
                      }
                      options={parentOptions}
                      isPending={treeQuery.isPending}
                      isError={treeQuery.isError}
                      onRetry={() => void treeQuery.refetch()}
                      disabled={disabled}
                      labels={{
                        placeholder: t('productCategories.form.parentPlaceholder'),
                        searchPlaceholder: t('productCategories.form.parentSearch'),
                        empty: t('productCategories.form.parentEmpty'),
                        noMatch: t('productCategories.form.parentNoMatch'),
                        error: t('productCategories.form.parentError'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('productCategories.form.description')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {attributesVisible && (
            <FormSection
              icon={ListChecks}
              title={t('productCategories.form.sections.attributes.title')}
              description={t('productCategories.form.sections.attributes.description')}
            >
              {parentId !== null && (
                <MetaField
                  control={form.control}
                  name="inherits_attributes"
                  metaKey="inherits_attributes"
                  label={t('productCategories.form.inheritsAttributes')}
                  description={
                    <FormDescription>
                      {t('productCategories.form.inheritsAttributesHint')}
                    </FormDescription>
                  }
                >
                  {({ field, disabled }) => (
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={disabled}
                      />
                    </FormControl>
                  )}
                </MetaField>
              )}

              <MetaField
                control={form.control}
                name="attributes"
                metaKey="attributes"
                label={t('productCategories.form.attributes')}
              >
                {({ field, disabled }) => (
                  <AttributeAssignmentEditor
                    value={field.value}
                    onChange={field.onChange}
                    inherited={inherited}
                    disabled={disabled}
                  />
                )}
              </MetaField>
            </FormSection>
          )}

          <CustomFieldsSection resource="product-categories" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('productCategories.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('productCategories.form.saving')
                : t('productCategories.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
