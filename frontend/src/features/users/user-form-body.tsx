import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  AccessTabContent,
  AddressesTabContent,
  ContactsTabContent,
  CredentialsTabContent,
  IdentityTabContent,
} from '@/features/users/user-form-account-tabs'
import { ContractDataTabContent } from '@/features/users/user-form-contract-data-tab'
import { ContractTabContent, ProfileTabContent } from '@/features/users/user-form-employment-tabs'
import { useUserForm } from '@/features/users/use-user-form'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserDetail } from '@/features/users/types'

interface UserFormBodyProps {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
  onAvatarChange?: () => void
}

/** Small dot marking a tab that carries a validation error (AC-014). */
function TabErrorDot({ label }: { label: string }) {
  return (
    <span className="size-1.5 shrink-0 rounded-full bg-destructive" role="img" aria-label={label} />
  )
}

/**
 * The user create/edit form UI, redesigned as a tabbed layout (spec 0015):
 * Identity, Credentials, Access, Profile, Contract, Contract data, Contacts,
 * Addresses. Every field is wrapped in `MetaField` (spec 0004): hidden fields
 * are absent, non-editable fields render disabled/read-only, `required` comes
 * from the resolved `ResourcePermissions` — no hardcoded permission logic
 * lives here. All non-render logic lives in `useUserForm`; each tab's content
 * lives in a sibling module (`user-form-account-tabs.tsx`,
 * `user-form-employment-tabs.tsx`, `user-form-contract-data-tab.tsx`) so this
 * orchestration file stays within the engineering size limits.
 */
export function UserFormBody({ mode, onSuccess, onCancel, onAvatarChange }: UserFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    profileValid,
    selectedRoleItems,
    selectedBusinessFunctionItem,
    selectedCompanyItem,
    selectedOperationalSiteItem,
    selectedReportsToItem,
    onSubmit,
    setPendingAvatar,
    handleAvatarUpload,
    handleAvatarRemove,
    canUploadAvatar,
    canRemoveAvatar,
    personalDataFieldPermission,
  } = useUserForm({ mode, onSuccess, onAvatarChange })

  // The identity card's data is still loading/failed (edit mode only): show a
  // single skeleton/retry in its place, and hold off on contacts/addresses
  // (their buffers are not seeded yet either) instead of rendering empty rows.
  const isProfileLoading = isEdit && profileQuery.isPending
  const isProfileError = isEdit && profileQuery.isError

  // Whole-tab visibility, read from the same authorization context `MetaField`
  // uses: a tab is only worth rendering if at least one of its fields is
  // visible. `MetaField` still gates each field individually — this only
  // decides whether the surrounding tab is shown at all. Identity has no
  // permission-gated field of its own, so it is always shown.
  const credentialsVisible =
    fieldPermission('email').visible || fieldPermission('password').visible
  const accessVisible = fieldPermission('roles').visible
  const profileVisible =
    fieldPermission('employment.business_function_id').visible ||
    fieldPermission('employment.is_manager').visible ||
    fieldPermission('employment.job_description').visible ||
    fieldPermission('employment.reports_to_id').visible
  const contractVisible =
    fieldPermission('employment.relationship_type').visible ||
    fieldPermission('employment.company_id').visible ||
    fieldPermission('employment.operational_site_id').visible
  const contractDataVisible =
    fieldPermission('employment.qualification_type').visible ||
    fieldPermission('employment.hired_at').visible ||
    fieldPermission('employment.terminated_at').visible ||
    fieldPermission('employment.standard_daily_minutes').visible ||
    fieldPermission('employment.break_daily_minutes').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible

  // Per-tab error indicator (AC-014): RHF field errors for the tab's own
  // fields, plus the buffered personal-data draft's own (schema-driven)
  // validity for Identity, since that buffer lives outside RHF.
  const errors = form.formState.errors
  const tabHasErrorsLabel = t('users.form.tabs.tabHasErrors')
  const identityHasError = !profileValid
  const credentialsHasError = Boolean(
    errors.email || errors.password || errors.password_confirmation,
  )
  const accessHasError = Boolean(errors.roles)
  const profileHasError = Boolean(
    errors.employment?.business_function_id ||
      errors.employment?.is_manager ||
      errors.employment?.job_description ||
      errors.employment?.reports_to_id,
  )
  const contractHasError = Boolean(
    errors.employment?.relationship_type ||
      errors.employment?.company_id ||
      errors.employment?.operational_site_id,
  )
  const contractDataHasError = Boolean(
    errors.employment?.qualification_type ||
      errors.employment?.hired_at ||
      errors.employment?.terminated_at ||
      errors.employment?.standard_daily_minutes ||
      errors.employment?.break_daily_minutes,
  )

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <Tabs defaultValue="identity" className="flex flex-1 flex-col gap-4">
            <TabsList>
              <TabsTrigger value="identity">
                {t('users.form.tabs.identity')}
                {identityHasError && <TabErrorDot label={tabHasErrorsLabel} />}
              </TabsTrigger>
              {credentialsVisible && (
                <TabsTrigger value="credentials">
                  {t('users.form.tabs.credentials')}
                  {credentialsHasError && <TabErrorDot label={tabHasErrorsLabel} />}
                </TabsTrigger>
              )}
              {accessVisible && (
                <TabsTrigger value="access">
                  {t('users.form.tabs.access')}
                  {accessHasError && <TabErrorDot label={tabHasErrorsLabel} />}
                </TabsTrigger>
              )}
              {profileVisible && (
                <TabsTrigger value="profile">
                  {t('users.form.tabs.profile')}
                  {profileHasError && <TabErrorDot label={tabHasErrorsLabel} />}
                </TabsTrigger>
              )}
              {contractVisible && (
                <TabsTrigger value="contract">
                  {t('users.form.tabs.contract')}
                  {contractHasError && <TabErrorDot label={tabHasErrorsLabel} />}
                </TabsTrigger>
              )}
              {contractDataVisible && (
                <TabsTrigger value="contractData">
                  {t('users.form.tabs.contractData')}
                  {contractDataHasError && <TabErrorDot label={tabHasErrorsLabel} />}
                </TabsTrigger>
              )}
              {!isProfileLoading && !isProfileError && contactsVisible && (
                <TabsTrigger value="contacts">{t('users.form.tabs.contacts')}</TabsTrigger>
              )}
              {!isProfileLoading && !isProfileError && addressesVisible && (
                <TabsTrigger value="addresses">{t('users.form.tabs.addresses')}</TabsTrigger>
              )}
            </TabsList>

            <TabsContent value="identity" className="flex flex-col gap-4">
              <IdentityTabContent
                mode={mode}
                profileName={profileName}
                isLoading={isProfileLoading}
                isError={isProfileError}
                onRetry={() => profileQuery.refetch()}
                profileDraft={profileDraft}
                setProfileDraft={setProfileDraft}
                personalDataFieldPermission={personalDataFieldPermission}
                setPendingAvatar={setPendingAvatar}
                handleAvatarUpload={handleAvatarUpload}
                handleAvatarRemove={handleAvatarRemove}
                canUploadAvatar={canUploadAvatar}
                canRemoveAvatar={canRemoveAvatar}
              />
            </TabsContent>

            {credentialsVisible && (
              <TabsContent value="credentials" className="flex flex-col gap-4">
                <CredentialsTabContent control={form.control} isEdit={isEdit} />
              </TabsContent>
            )}

            {accessVisible && (
              <TabsContent value="access" className="flex flex-col gap-4">
                <AccessTabContent control={form.control} selectedRoleItems={selectedRoleItems} />
              </TabsContent>
            )}

            {profileVisible && (
              <TabsContent value="profile" className="flex flex-col gap-4">
                <ProfileTabContent
                  control={form.control}
                  selectedBusinessFunctionItem={selectedBusinessFunctionItem}
                  selectedReportsToItem={selectedReportsToItem}
                />
              </TabsContent>
            )}

            {contractVisible && (
              <TabsContent value="contract" className="flex flex-col gap-4">
                <ContractTabContent
                  control={form.control}
                  selectedCompanyItem={selectedCompanyItem}
                  selectedOperationalSiteItem={selectedOperationalSiteItem}
                />
              </TabsContent>
            )}

            {contractDataVisible && (
              <TabsContent value="contractData" className="flex flex-col gap-4">
                <ContractDataTabContent control={form.control} />
              </TabsContent>
            )}

            {!isProfileLoading && !isProfileError && contactsVisible && (
              <TabsContent value="contacts" className="flex flex-col gap-4">
                <ContactsTabContent
                  profileDraft={profileDraft}
                  setProfileDraft={setProfileDraft}
                  personalDataFieldPermission={personalDataFieldPermission}
                />
              </TabsContent>
            )}

            {!isProfileLoading && !isProfileError && addressesVisible && (
              <TabsContent value="addresses" className="flex flex-col gap-4">
                <AddressesTabContent
                  profileDraft={profileDraft}
                  setProfileDraft={setProfileDraft}
                  personalDataFieldPermission={personalDataFieldPermission}
                />
              </TabsContent>
            )}
          </Tabs>

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
              {t('users.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('users.form.saving') : t('users.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
