import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useConfirm } from '@/components/confirm-dialog-context'
import { BankForm } from '@/features/company-sites/bank-form'
import type { BankDraft } from '@/features/company-sites/types'

let bankKeyCounter = 0

/** A process-stable, collision-free key for a brand new buffered bank row. */
function nextBankKey(): string {
  bankKeyCounter += 1
  return `bank-draft-${bankKeyCounter}`
}

interface BanksManagerProps {
  /** The buffered banks owned by the parent form. */
  value: BankDraft[]
  /** Emits the next buffer after any add/edit/remove. */
  onChange: (next: BankDraft[]) => void
  /** `!editable` renders the list read-only (no add/edit/remove). */
  readOnly?: boolean
}

/** `new` = the add form is open; a string = that bank `_key` is being edited. */
type EditingState = 'new' | string | null

/**
 * Reusable CRUD list for a site's banks, cloned from `ContactsManager` but
 * always buffered (spec 0020: `banks[]` is sent authoritatively with the rest
 * of the site payload — there is no per-row endpoint to persist against
 * immediately). The create/edit form opens in a dialog. The site's preferred
 * bank is a per-row `is_primary` flag toggled in that form; the list keeps at
 * most one primary (enforcePrimary), mirroring contacts/addresses.
 */
export function BanksManager({ value, onChange, readOnly = false }: BanksManagerProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [editing, setEditing] = useState<EditingState>(null)

  // Demotes every other bank when one is marked preferred, so the list keeps at
  // most one primary (mirrors ContactsManager's single-primary rule; here the
  // whole list is the scope, there is no per-type dimension).
  const enforcePrimary = (banks: BankDraft[], primaryKey: string): BankDraft[] =>
    banks.map((bank) =>
      bank._key !== primaryKey && bank.is_primary ? { ...bank, is_primary: false } : bank,
    )

  const handleAdd = (fields: Omit<BankDraft, '_key'>) => {
    const draft: BankDraft = { ...fields, _key: nextBankKey() }
    const next = [...value, draft]
    onChange(draft.is_primary ? enforcePrimary(next, draft._key) : next)
    setEditing(null)
  }

  const handleEdit = (key: string, fields: Omit<BankDraft, '_key'>) => {
    const draft: BankDraft = { ...fields, _key: key }
    const next = value.map((bank) => (bank._key === key ? draft : bank))
    onChange(draft.is_primary ? enforcePrimary(next, key) : next)
    setEditing(null)
  }

  const handleDelete = async (key: string) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('companySites.form.banks.deleteAction'),
      description: t('companySites.form.banks.deleteConfirm'),
      confirmLabel: t('companySites.form.banks.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    onChange(value.filter((bank) => bank._key !== key))
  }

  const editingBank =
    typeof editing === 'string' && editing !== 'new'
      ? value.find((bank) => bank._key === editing)
      : undefined

  return (
    <section className="flex flex-col gap-2">
      {value.length === 0 && (
        <p className="text-sm text-muted-foreground">{t('companySites.form.banks.empty')}</p>
      )}

      <ul className="flex flex-col gap-2">
        {value.map((bank) => (
          <li key={bank._key} className="flex flex-wrap items-center gap-2 rounded-lg border p-3">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
              <Landmark className="size-4" aria-hidden="true" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-sm font-medium">{bank.name}</span>
              {bank.iban && (
                <span className="truncate text-xs text-muted-foreground">{bank.iban}</span>
              )}
            </div>
            {bank.is_primary && (
              <Badge variant="secondary">{t('companySites.form.banks.preferredBadge')}</Badge>
            )}
            {!readOnly && (
              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('companySites.form.banks.editAction')}
                  onClick={() => setEditing(bank._key)}
                >
                  <Pencil aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('companySites.form.banks.deleteAction')}
                  onClick={() => handleDelete(bank._key)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            )}
          </li>
        ))}
      </ul>

      {!readOnly && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => setEditing('new')}
          className="self-start"
        >
          <Plus aria-hidden="true" />
          {t('companySites.form.banks.add')}
        </Button>
      )}

      <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent aria-describedby={undefined}>
          <DialogHeader>
            <DialogTitle>
              {editing === 'new'
                ? t('companySites.form.banks.add')
                : t('companySites.form.banks.editAction')}
            </DialogTitle>
          </DialogHeader>
          {editing !== null && (
            <BankForm
              bank={editingBank}
              onSubmit={editing === 'new' ? handleAdd : (fields) => handleEdit(editing, fields)}
              onCancel={() => setEditing(null)}
            />
          )}
        </DialogContent>
      </Dialog>
    </section>
  )
}

/** Count badge for the Banks tab trigger, mirroring the contacts/addresses tabs. */
export function BanksCountBadge({ count }: { count: number }) {
  return <Badge variant="secondary">{count}</Badge>
}
