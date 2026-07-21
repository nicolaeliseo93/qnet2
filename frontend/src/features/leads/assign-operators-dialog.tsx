import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Scale, UserCheck } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Precompiled Sede seed (spec 0048 AC-031), when the selection shares one. */
export interface AssignOperatorsDialogSite {
  id: number
  label: string
}

type AssignmentMode = 'single' | 'balanced'

/** Input handed to `onAssign`, mirroring the `POST /leads/assign-operators` request body. */
export interface AssignOperatorsDialogInput {
  operational_site_id: number
  mode: AssignmentMode
  operator_id?: number
}

/** The two assignment modes, in selection order, each with its lead icon. */
const ASSIGNMENT_MODES: ReadonlyArray<{ mode: AssignmentMode; icon: LucideIcon }> = [
  { mode: 'balanced', icon: Scale },
  { mode: 'single', icon: UserCheck },
]

export interface AssignOperatorsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many leads/rows are selected; drives the title/description copy. */
  selectionCount: number
  defaultSiteId?: number | null
  defaultSite?: AssignOperatorsDialogSite | null
  /**
   * Wired by the consumer to its own endpoint (the Lead table via
   * `useAssignOperators`, the import review bar via its own PATCH). The
   * dialog never calls the API itself: it only collects the input, shows a
   * pending state, and closes on success. A rejection is assumed already
   * surfaced by the caller (toast) and just keeps the dialog open with the
   * current picks so the user can retry.
   */
  onAssign: (input: AssignOperatorsDialogInput) => Promise<void>
}

/**
 * Shared "Assegna operatori" popup (spec 0048). The flow is sequential: the
 * user first picks the assignment mode — "Smistamento equo" (`balanced`) or
 * "Assegna a operatore" (`single`) — then the Sede, and (only for `single`)
 * the Operatore filtered by that Sede (AC-030). A single confirm action
 * commits the pick. Reused as-is by the Lead table's bulk action and the
 * import review bar.
 */
export function AssignOperatorsDialog({
  open,
  onOpenChange,
  selectionCount,
  defaultSiteId,
  defaultSite,
  onAssign,
}: AssignOperatorsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-sm">
        <AssignOperatorsDialogBody
          selectionCount={selectionCount}
          defaultSiteId={defaultSiteId}
          defaultSite={defaultSite}
          onAssign={onAssign}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface AssignOperatorsDialogBodyProps {
  selectionCount: number
  defaultSiteId?: number | null
  defaultSite?: AssignOperatorsDialogSite | null
  onAssign: AssignOperatorsDialogProps['onAssign']
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed, so keeping this
 * state in its own component (rather than in `AssignOperatorsDialog` itself,
 * which the consumer keeps mounted across opens) is what makes every open a
 * fresh selection instead of carrying over the previous one.
 */
function AssignOperatorsDialogBody({
  selectionCount,
  defaultSiteId,
  defaultSite,
  onAssign,
  onClose,
}: AssignOperatorsDialogBodyProps) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<AssignmentMode | null>(null)
  const [siteId, setSiteId] = useState<number | null>(defaultSiteId ?? defaultSite?.id ?? null)
  const [operatorId, setOperatorId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // A Sede change re-scopes the whole operator list: the previous pick can
  // no longer be assumed to belong to it, so it is always cleared.
  function handleSiteChange(nextSiteId: number | null) {
    setSiteId(nextSiteId)
    setOperatorId(null)
  }

  // Single needs both Sede and Operatore; balanced needs only the Sede.
  const canSubmit =
    mode !== null && siteId !== null && (mode === 'balanced' || operatorId !== null)

  function handleAssign() {
    if (!canSubmit || siteId === null) {
      return
    }
    setIsSubmitting(true)
    onAssign({
      operational_site_id: siteId,
      mode: mode as AssignmentMode,
      ...(mode === 'single' ? { operator_id: operatorId as number } : {}),
    })
      .then(() => onClose())
      .catch(() => {
        // Already surfaced via toast by the caller; keep the picks so the
        // user can retry without reselecting.
      })
      .finally(() => setIsSubmitting(false))
  }

  const ConfirmIcon = mode === 'single' ? UserCheck : Scale

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('leads.assign.title')}</DialogTitle>
        <DialogDescription>{t('leads.assign.description', { count: selectionCount })}</DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        {/* Step 1: pick the assignment mode. */}
        <div className="space-y-1.5">
          <Label>{t('leads.assign.mode.label')}</Label>
          <div role="radiogroup" aria-label={t('leads.assign.mode.label')} className="flex flex-col gap-2">
            {ASSIGNMENT_MODES.map(({ mode: value, icon: Icon }) => {
              const selected = mode === value
              return (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={selected}
                  aria-label={t(`leads.assign.actions.${value}`)}
                  disabled={isSubmitting}
                  onClick={() => setMode(value)}
                  className={cn(
                    // bg-card (white) lifts each option off the gray dialog
                    // surface (bg-background); a bare border blended tone-on-tone.
                    'flex items-start gap-2.5 rounded-lg border bg-card px-3 py-2 text-left transition-colors',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    'disabled:pointer-events-none disabled:opacity-50',
                    selected
                      ? 'border-primary ring-1 ring-primary/40 text-foreground'
                      : 'border-border text-muted-foreground hover:text-foreground hover:bg-muted/60',
                  )}
                >
                  <Icon
                    aria-hidden="true"
                    className={cn('mt-0.5 size-3.5 shrink-0', selected ? 'text-primary' : 'text-muted-foreground')}
                  />
                  <span className="space-y-0.5">
                    <span className="block text-xs font-medium text-foreground">
                      {t(`leads.assign.actions.${value}`)}
                    </span>
                    <span className="block text-xs text-muted-foreground">
                      {t(`leads.assign.actions.${value}Hint`)}
                    </span>
                  </span>
                </button>
              )
            })}
          </div>
        </div>

        {/* Step 2: pick the Sede (always) and the Operatore (single only). */}
        {mode !== null && (
          <div className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="assign-operators-site">{t('leads.assign.site.label')}</Label>
              <AsyncPaginatedSelect
                id="assign-operators-site"
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                value={siteId}
                onChange={handleSiteChange}
                selectedItem={defaultSite ? { id: defaultSite.id, label: defaultSite.label } : null}
                disabled={isSubmitting}
                className="h-8 text-xs"
                labels={{
                  placeholder: t('leads.assign.site.placeholder'),
                  searchPlaceholder: t('leads.assign.site.searchPlaceholder'),
                  empty: t('leads.assign.site.empty'),
                  error: t('leads.assign.site.selectError'),
                  clearLabel: t('leads.assign.site.selectClear'),
                  triggerLabel: t('leads.assign.site.label'),
                  retry: t('leads.assign.site.retry'),
                }}
              />
            </div>

            {mode === 'single' && (
              <div className="space-y-1.5">
                <Label htmlFor="assign-operators-operator">{t('leads.assign.operator.label')}</Label>
                <AsyncPaginatedSelect
                  id="assign-operators-operator"
                  resource={USERS_FOR_SELECT_RESOURCE}
                  value={operatorId}
                  onChange={setOperatorId}
                  showAvatar
                  disabled={isSubmitting || siteId === null}
                  params={siteId !== null ? { operational_site_id: siteId } : undefined}
                  className="h-8 text-xs"
                  labels={{
                    placeholder: t('leads.assign.operator.placeholder'),
                    searchPlaceholder: t('leads.assign.operator.searchPlaceholder'),
                    empty: t('leads.assign.operator.empty'),
                    error: t('leads.assign.operator.selectError'),
                    clearLabel: t('leads.assign.operator.selectClear'),
                    triggerLabel: t('leads.assign.operator.label'),
                    retry: t('leads.assign.operator.retry'),
                  }}
                />
                <p className="text-xs text-muted-foreground">
                  {siteId === null ? t('leads.assign.operator.disabledHint') : t('leads.assign.operator.hint')}
                </p>
              </div>
            )}
          </div>
        )}
      </div>

      <DialogFooter>
        <Button
          type="button"
          size="sm"
          className="w-full gap-1.5"
          onClick={handleAssign}
          disabled={!canSubmit || isSubmitting}
        >
          <ConfirmIcon className="size-3.5" aria-hidden="true" />
          {isSubmitting ? t('leads.assign.actions.assigning') : t('leads.assign.actions.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}
