import { Archive, Building2, Landmark, Settings, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  FORM_TAB_LIST_CLASS,
  FORM_TAB_TRIGGER_CLASS,
  TabErrorDot,
} from '@/components/form-tab-strip'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProfileTabContent } from '@/features/company-sites/company-site-profile-tab'
import { SettingsTabContent } from '@/features/company-sites/company-site-settings-tab'
import { BanksTabContent } from '@/features/company-sites/company-site-banks-tab'
import { OtherTabContent } from '@/features/company-sites/company-site-other-tab'
import { OTHER_FIELD_KEYS } from '@/features/company-sites/company-site-other-fields'
import { useCompanySiteForm } from '@/features/company-sites/use-company-site-form'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { CompanySiteDetail } from '@/features/company-sites/types'

interface CompanySiteFormBodyProps {
  mode: CompanySiteFormMode
  onSuccess: (companySite: CompanySiteDetail) => void
  onCancel: () => void
  onSiteChange?: () => void
}

/** One entry in the tab strip: its value, label, icon, and gating flags. */
interface CompanySiteFormTab {
  value: string
  label: string
  Icon: LucideIcon
  visible: boolean
  hasError: boolean
}

/**
 * The company-site create/edit form UI, organized into the four tabs of spec
 * 0020: Profilo (identity, logo, address), Impostazioni (responsibles,
 * default bank, progressives, read-only quotation ids), Banche (the inline
 * banks collection) and Altro (always read-only). Every editable field is
 * wrapped in `MetaField` (spec 0004): hidden fields are absent, non-editable
 * fields render disabled, `required` comes from the resolved
 * `ResourcePermissions`. All non-render logic lives in `useCompanySiteForm`;
 * each tab's content lives in a sibling module so this file stays within the
 * size limits (engineering.md §6).
 */
export function CompanySiteFormBody({
  mode,
  onSuccess,
  onCancel,
  onSiteChange,
}: CompanySiteFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    serverError,
    profileDraft,
    setProfileDraft,
    profileValid,
    personalDataFieldPermission,
    banksDraft,
    setBanksDraft,
    selectedResponsibleRdaItem,
    selectedResponsibleTicketsItem,
    selectedResponsibleValidationContractsItem,
    selectedResponsibleValidationContractsTwoItem,
    onSubmit,
    setPendingLogo,
    handleLogoUpload,
    handleLogoRemove,
    canUploadLogo,
    canRemoveLogo,
    canSetDefault,
    settingDefault,
    handleSetDefault,
  } = useCompanySiteForm({ mode, onSuccess, onSiteChange })

  const siteName = useWatch({ control: form.control, name: 'name' }) || ''
  const companySite = mode.type === 'edit' ? mode.companySite : null

  // Whole-tab visibility, read from the same authorization context `MetaField`
  // uses: a tab is only worth rendering if at least one of its fields is
  // visible. Profilo has mandatory name/email, so it is always shown.
  const settingsVisible =
    fieldPermission('responsible_rda_id').visible ||
    fieldPermission('responsible_tickets_id').visible ||
    fieldPermission('responsible_validation_contracts_id').visible ||
    fieldPermission('responsible_validation_contracts_two_id').visible ||
    fieldPermission('default_bank_id').visible ||
    fieldPermission('proforma_progressive').visible ||
    fieldPermission('invoice_progressive').visible
  const banksPermission = fieldPermission('banks')
  const banksVisible = banksPermission.visible
  const banksReadOnly = banksPermission.disabled || !banksPermission.editable
  const otherVisible = OTHER_FIELD_KEYS.some((key) => fieldPermission(key).visible)

  const errors = form.formState.errors
  const tabHasErrorsLabel = t('companySites.form.tabs.tabHasErrors')
  // Profilo error = the mandatory company card is invalid (its buffer lives
  // outside RHF) or the site's own name/notes carry a validation error.
  const profileHasError = !profileValid || Boolean(errors.name || errors.notes)
  const settingsHasError = Boolean(
    errors.responsible_rda_id ||
      errors.responsible_tickets_id ||
      errors.responsible_validation_contracts_id ||
      errors.responsible_validation_contracts_two_id ||
      errors.default_bank_id ||
      errors.proforma_progressive ||
      errors.invoice_progressive,
  )

  const tabItems: CompanySiteFormTab[] = [
    { value: 'profile', label: t('companySites.form.tabs.profile'), Icon: Building2, visible: true, hasError: profileHasError },
    { value: 'settings', label: t('companySites.form.tabs.settings'), Icon: Settings, visible: settingsVisible, hasError: settingsHasError },
    { value: 'banks', label: t('companySites.form.tabs.banks'), Icon: Landmark, visible: banksVisible, hasError: false },
    { value: 'other', label: t('companySites.form.tabs.other'), Icon: Archive, visible: otherVisible, hasError: false },
  ]

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <Tabs defaultValue="profile" className="flex flex-1 flex-col gap-4">
            <TabsList className={FORM_TAB_LIST_CLASS}>
              {tabItems
                .filter((tab) => tab.visible)
                .map(({ value, label, Icon, hasError }) => (
                  <TabsTrigger key={value} value={value} className={FORM_TAB_TRIGGER_CLASS}>
                    <Icon aria-hidden="true" />
                    {label}
                    {hasError && <TabErrorDot label={tabHasErrorsLabel} />}
                  </TabsTrigger>
                ))}
            </TabsList>

            <TabsContent value="profile" className="flex flex-col gap-4">
              <ProfileTabContent
                mode={mode}
                control={form.control}
                siteName={siteName}
                profileDraft={profileDraft}
                setProfileDraft={setProfileDraft}
                personalDataFieldPermission={personalDataFieldPermission}
                onLogoFileSelected={setPendingLogo}
                onLogoUpload={handleLogoUpload}
                onLogoRemove={handleLogoRemove}
                canUploadLogo={canUploadLogo}
                canRemoveLogo={canRemoveLogo}
              />
            </TabsContent>

            {settingsVisible && (
              <TabsContent value="settings" className="flex flex-col gap-4">
                <SettingsTabContent
                  control={form.control}
                  companySite={companySite}
                  banksDraft={banksDraft}
                  selectedResponsibleRdaItem={selectedResponsibleRdaItem}
                  selectedResponsibleTicketsItem={selectedResponsibleTicketsItem}
                  selectedResponsibleValidationContractsItem={
                    selectedResponsibleValidationContractsItem
                  }
                  selectedResponsibleValidationContractsTwoItem={
                    selectedResponsibleValidationContractsTwoItem
                  }
                />
              </TabsContent>
            )}

            {banksVisible && (
              <TabsContent value="banks" className="flex flex-col gap-4">
                <BanksTabContent
                  banksDraft={banksDraft}
                  setBanksDraft={setBanksDraft}
                  readOnly={banksReadOnly}
                />
              </TabsContent>
            )}

            {otherVisible && (
              <TabsContent value="other" className="flex flex-col gap-4">
                <OtherTabContent companySite={companySite} />
              </TabsContent>
            )}
          </Tabs>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            {canSetDefault && (
              <Button
                type="button"
                variant="secondary"
                onClick={() => void handleSetDefault()}
                disabled={settingDefault || form.formState.isSubmitting}
              >
                {settingDefault
                  ? t('companySites.form.settingDefault')
                  : t('companySites.form.setDefault')}
              </Button>
            )}
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('companySites.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('companySites.form.saving')
                : t('companySites.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
