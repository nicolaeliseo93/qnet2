import { useTranslation } from 'react-i18next'
import { Landmark } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { BanksCountBadge, BanksManager } from '@/features/company-sites/banks-manager'
import type { BankDraft } from '@/features/company-sites/types'

interface BanksTabContentProps {
  banksDraft: BankDraft[]
  setBanksDraft: (next: BankDraft[]) => void
  readOnly: boolean
}

/** Banche tab: the site's inline 1→N banks collection (spec 0020). */
export function BanksTabContent({ banksDraft, setBanksDraft, readOnly }: BanksTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Landmark}
      title={t('companySites.form.sections.banks.title')}
      description={t('companySites.form.sections.banks.description')}
      aside={<BanksCountBadge count={banksDraft.length} />}
    >
      <BanksManager value={banksDraft} onChange={setBanksDraft} readOnly={readOnly} />
    </FormSection>
  )
}
