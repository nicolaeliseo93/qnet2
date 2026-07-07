import { useTranslation } from 'react-i18next'
import { ChevronRight, FolderPlus, Package, Pencil, SlidersHorizontal, Trash2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Can } from '@/features/auth/can'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Px indent applied per depth level, so the hierarchy reads at a glance. */
const DEPTH_INDENT_PX = 18

export interface ProductCategoryTreeActions {
  onAddChild: (node: ProductCategoryTreeNode) => void
  onEdit: (node: ProductCategoryTreeNode) => void
  onDelete: (node: ProductCategoryTreeNode) => void
}

interface ProductCategoryTreeNodeRowProps extends ProductCategoryTreeActions {
  node: ProductCategoryTreeNode
  depth: number
  expandedIds: Set<number>
  onToggle: (id: number) => void
}

/**
 * A single row of the category tree, recursing into its own children. Kept as
 * a module-scoped component (never defined inside another component) so its
 * identity stays stable across re-renders (engineering.md, react rules).
 * Deliberately a lightweight expand/collapse list rather than a full WAI-ARIA
 * `treeitem` widget with roving tabindex/arrow-key navigation — out of scope
 * for spec 0017's compact tree view; every affordance is still a real,
 * labeled button reachable by keyboard.
 */
export function ProductCategoryTreeNodeRow({
  node,
  depth,
  expandedIds,
  onToggle,
  onAddChild,
  onEdit,
  onDelete,
}: ProductCategoryTreeNodeRowProps) {
  const { t } = useTranslation()
  const hasChildren = node.children.length > 0
  const isExpanded = expandedIds.has(node.id)

  return (
    <Collapsible open={isExpanded} onOpenChange={() => onToggle(node.id)}>
      <div
        className="flex items-center gap-1.5 rounded-md py-1 pr-1.5 text-sm hover:bg-accent/50"
        style={{ paddingLeft: depth * DEPTH_INDENT_PX + 4 }}
      >
        {hasChildren ? (
          <CollapsibleTrigger asChild>
            <button
              type="button"
              aria-label={t(
                isExpanded ? 'productCategories.tree.collapse' : 'productCategories.tree.expand',
                { name: node.name },
              )}
              className="flex size-5 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:text-foreground"
            >
              <ChevronRight
                className={cn('size-3.5 transition-transform', isExpanded && 'rotate-90')}
                aria-hidden="true"
              />
            </button>
          </CollapsibleTrigger>
        ) : (
          <span className="size-5 shrink-0" />
        )}

        <span className="min-w-0 flex-1 truncate font-medium">{node.name}</span>

        <Badge variant="outline" className="gap-1 text-[0.65rem]">
          <SlidersHorizontal className="size-3" aria-hidden="true" />
          {node.attributes_count}
        </Badge>
        <Badge variant="outline" className="gap-1 text-[0.65rem]">
          <Package className="size-3" aria-hidden="true" />
          {node.products_count}
        </Badge>

        <Can permission="product-categories.create">
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('productCategories.tree.addChild', { name: node.name })}
            onClick={() => onAddChild(node)}
          >
            <FolderPlus aria-hidden="true" />
          </Button>
        </Can>
        <Can permission="product-categories.update">
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('productCategories.tree.edit', { name: node.name })}
            onClick={() => onEdit(node)}
          >
            <Pencil aria-hidden="true" />
          </Button>
        </Can>
        <Can permission="product-categories.delete">
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('productCategories.tree.delete', { name: node.name })}
            onClick={() => onDelete(node)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        </Can>
      </div>

      {hasChildren && (
        <CollapsibleContent>
          {node.children.map((child) => (
            <ProductCategoryTreeNodeRow
              key={child.id}
              node={child}
              depth={depth + 1}
              expandedIds={expandedIds}
              onToggle={onToggle}
              onAddChild={onAddChild}
              onEdit={onEdit}
              onDelete={onDelete}
            />
          ))}
        </CollapsibleContent>
      )}
    </Collapsible>
  )
}
