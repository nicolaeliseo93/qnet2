import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { useModuleOpenMode } from '@/features/modules/use-module-open-mode'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'

/**
 * Maps a kebab-case module `domain` (e.g. `company-sites`) to its camelCase
 * i18n namespace (`companySites`), which is how every module's strings are
 * keyed in the locale files. Single-word domains pass through unchanged.
 */
function moduleI18nNamespace(domain: string): string {
  return domain.replace(/-([a-z])/g, (_, char: string) => char.toUpperCase())
}

/** Which sheet (if any) is currently open and for which row — same shape every `*-table.tsx` used inline before the rewire. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

export interface UseModuleOpenerOptions {
  /**
   * Called after a successful create/update, modal mode only (the caller's
   * own grid refresh / stats invalidation — `useModuleOpener` itself is
   * domain-agnostic and owns neither). Page mode never calls it: the user
   * has navigated away from the grid, there is nothing to refresh.
   */
  onSaved?: () => void
}

export interface UseModuleOpenerResult {
  openCreate: () => void
  openView: (row: TableRow) => void
  openEdit: (row: TableRow) => void
  /** The modal Sheet when the resolved mode is `'modal'`, `null` in `'page'` mode. Render it once, anywhere in the table adapter's tree. */
  sheet: ReactNode
}

/**
 * Domain-generic replacement for the `SheetState`/`navigate` pair every
 * `*-table.tsx` used to hard-code (spec 0042). Resolves the module's
 * effective open mode (`useModuleOpenMode`) and instrades view/edit/create
 * accordingly: `'modal'` mounts the registry's `DetailScreen`/`FormScreen`
 * inside an owned `<Sheet>` (AC-011/018, same `sheet-width:${domain}` layout
 * key as before); `'page'` navigates to the module's deep-link routes
 * (AC-011/019) and never mounts a Sheet.
 */
export function useModuleOpener(domain: string, options: UseModuleOpenerOptions = {}): UseModuleOpenerResult {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const mode = useModuleOpenMode(domain)
  const entry = getModuleRegistryEntry(domain)
  // Every module-specific path below the registry lookup is a stable, known
  // route/component; this "basePath" fallback only exists so hooks stay
  // unconditional (rules-of-hooks) up to the invariant check at the bottom.
  const basePath = entry?.basePath ?? ''
  // Sheet titles/subtitles are keyed under the module's camelCase i18n
  // namespace, while `domain` is the kebab-case slug (spec 0042).
  const ns = moduleI18nNamespace(domain)

  const { onSaved } = options
  const [sheetState, setSheetState] = useState<SheetState>({ kind: 'none' })

  const closeSheet = useCallback(() => setSheetState({ kind: 'none' }), [])

  const openCreate = useCallback(() => {
    if (mode === OPEN_MODE_MODAL) {
      setSheetState({ kind: 'create' })
    } else {
      void navigate(`${basePath}/new`)
    }
  }, [mode, navigate, basePath])

  const openView = useCallback(
    (row: TableRow) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'view', row })
      } else {
        void navigate(`${basePath}/${row.id}`)
      }
    },
    [mode, navigate, basePath],
  )

  const openEdit = useCallback(
    (row: TableRow) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'edit', row })
      } else {
        void navigate(`${basePath}/${row.id}/edit`)
      }
    },
    [mode, navigate, basePath],
  )

  const handleSaved = useCallback(() => {
    closeSheet()
    onSaved?.()
  }, [closeSheet, onSaved])

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  if (!entry) {
    throw new Error(`useModuleOpener: "${domain}" is not registered in the module registry.`)
  }

  const { DetailScreen, FormScreen } = entry

  const sheet =
    mode === OPEN_MODE_MODAL ? (
      <Sheet open={sheetState.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${domain}`}>
          {sheetState.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t(`${ns}.detail.title`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.detail.subtitle`)}</SheetDescription>
              </SheetHeader>
              <DetailScreen id={sheetState.row.id} />
            </>
          )}

          {sheetState.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t(`${ns}.form.createTitle`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.form.createSubtitle`)}</SheetDescription>
              </SheetHeader>
              <FormScreen mode={{ type: 'create' }} onSuccess={handleSaved} onCancel={closeSheet} />
            </>
          )}

          {sheetState.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t(`${ns}.form.editTitle`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.form.editSubtitle`)}</SheetDescription>
              </SheetHeader>
              <FormScreen
                mode={{ type: 'edit', id: sheetState.row.id }}
                onSuccess={handleSaved}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    ) : null

  return { openCreate, openView, openEdit, sheet }
}
