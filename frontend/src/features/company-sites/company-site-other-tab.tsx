import { useTranslation } from 'react-i18next'
import { Archive } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { CompanySiteReadonlyField } from '@/features/company-sites/company-site-readonly-field'
import { OTHER_FIELDS } from '@/features/company-sites/company-site-other-fields'
import type { CompanySiteDetail } from '@/features/company-sites/types'

interface OtherTabContentProps {
  /** `null` in create mode: every field renders blank (still visible+readonly). */
  companySite: CompanySiteDetail | null
}

/**
 * Altro tab: every field is always read-only (spec 0020), so it renders via
 * `CompanySiteReadonlyField` rather than `MetaField` — see that component's
 * doc comment for why these are not RHF-registered.
 */
export function OtherTabContent({ companySite }: OtherTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Archive}
      title={t('companySites.form.sections.other.title')}
      description={t('companySites.form.sections.other.description')}
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {OTHER_FIELDS.map(({ key, labelKey }) => (
          <CompanySiteReadonlyField
            key={key}
            metaKey={key}
            label={t(`companySites.form.other.${labelKey}`)}
            value={companySite?.[key] as string | number | null | undefined}
          />
        ))}
      </div>
    </FormSection>
  )
}
