import { AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { ReferentDuplicateMatch } from '@/features/referents/duplicate-check-api'

/** i18n key of each `matched_on` criterion (spec 0037 data_contract). */
const MATCH_CRITERION_LABEL_KEYS: Record<string, string> = {
  email: 'referents.form.duplicateWarning.criteria.email',
  phone: 'referents.form.duplicateWarning.criteria.phone',
  mobile: 'referents.form.duplicateWarning.criteria.mobile',
  tax_code: 'referents.form.duplicateWarning.criteria.taxCode',
}

interface ReferentDuplicateWarningProps {
  matches: ReferentDuplicateMatch[]
}

/**
 * Non-blocking amber notice (spec 0037): lists the existing referents whose
 * contacts/tax code already match what the operator is typing. Purely
 * presentational — `role="status"` (aria-live polite, not `alert`) so
 * assistive tech announces it without interrupting; the save action is never
 * gated by it. Renders nothing without matches (AC-008).
 */
export function ReferentDuplicateWarning({ matches }: ReferentDuplicateWarningProps) {
  const { t } = useTranslation()

  if (matches.length === 0) {
    return null
  }

  return (
    <div
      role="status"
      className="flex flex-col gap-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
    >
      <span className="flex items-center gap-1.5 font-medium">
        <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
        {t('referents.form.duplicateWarning.title')}
      </span>
      <ul className="flex flex-col gap-1 pl-5">
        {matches.map((match) => (
          <li key={match.referent_id} className="list-disc">
            {t('referents.form.duplicateWarning.entry', {
              name: match.name,
              criteria: match.matched_on
                .map((criterion) => t(MATCH_CRITERION_LABEL_KEYS[criterion] ?? criterion))
                .join(', '),
            })}
          </li>
        ))}
      </ul>
    </div>
  )
}
