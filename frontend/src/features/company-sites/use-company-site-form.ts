import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createCompanySite,
  deleteCompanySiteLogo,
  setDefaultCompanySite,
  updateCompanySite,
  uploadCompanySiteLogo,
} from '@/features/company-sites/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/company-sites/company-site-form-payload'
import {
  buildCreateCompanySiteSchema,
  buildUpdateCompanySiteSchema,
  type CreateCompanySiteFormValues,
  type UpdateCompanySiteFormValues,
} from '@/features/company-sites/company-site-schema'
import type { BankDraft, CompanySiteDetail } from '@/features/company-sites/types'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { ForSelectItem } from '@/features/for-select/types'

export type CompanySiteFormValues = CreateCompanySiteFormValues & UpdateCompanySiteFormValues

/** The blank address block a new site (or one without an address) starts from. */
const EMPTY_ADDRESS: CompanySiteFormValues['address'] = {
  line1: '',
  line2: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'email',
  'fiscal_code',
  'vat_number',
  'phone',
  'pec',
  'fax',
  'notes',
  'address.line1',
  'address.line2',
  'address.postal_code',
  'address.country_id',
  'address.state_id',
  'address.province_id',
  'address.city_id',
  'responsible_rda_id',
  'responsible_tickets_id',
  'responsible_validation_contracts_id',
  'responsible_validation_contracts_two_id',
  'default_bank_id',
  'proforma_progressive',
  'invoice_progressive',
] as const

interface UseCompanySiteFormArgs {
  mode: CompanySiteFormMode
  onSuccess: (companySite: CompanySiteDetail) => void
  onSiteChange?: () => void
}

/**
 * Owns every non-render concern of `CompanySiteForm`: RHF/Zod wiring, the
 * buffered banks collection, the deferred/immediate logo mutations, the
 * set-default action, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useCompanySiteForm({ mode, onSuccess, onSiteChange }: UseCompanySiteFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { canAction } = useResourcePermissions()
  const [serverError, setServerError] = useState<string | null>(null)
  // CREATE mode only: logo chosen before the site exists, uploaded after save.
  const [pendingLogo, setPendingLogo] = useState<File | null>(null)

  const isEdit = mode.type === 'edit'

  // The buffered banks collection (mirrors `ContactsManager`'s pattern): lives
  // outside RHF, seeded once from the loaded site and submitted as the
  // authoritative `banks[]` array on save.
  const [banksDraft, setBanksDraft] = useState<BankDraft[]>(() =>
    mode.type === 'edit'
      ? mode.companySite.banks.map((bank) => ({ _key: `bank-${bank.id}`, ...bank }))
      : [],
  )

  const schema = useMemo(
    () => (isEdit ? buildUpdateCompanySiteSchema(t) : buildCreateCompanySiteSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<CompanySiteFormValues>(() => {
    if (mode.type === 'edit') {
      const site = mode.companySite
      const address = site.address
      return {
        name: site.name,
        email: site.email,
        fiscal_code: site.fiscal_code ?? '',
        vat_number: site.vat_number ?? '',
        phone: site.phone ?? '',
        pec: site.pec ?? '',
        fax: site.fax ?? '',
        notes: site.notes ?? '',
        address: address
          ? {
              line1: address.line1,
              line2: address.line2 ?? '',
              postal_code: address.postal_code ?? '',
              country_id: address.country_id,
              state_id: address.state_id,
              province_id: address.province_id,
              city_id: address.city_id,
            }
          : EMPTY_ADDRESS,
        responsible_rda_id: site.responsible_rda_id,
        responsible_tickets_id: site.responsible_tickets_id,
        responsible_validation_contracts_id: site.responsible_validation_contracts_id,
        responsible_validation_contracts_two_id: site.responsible_validation_contracts_two_id,
        default_bank_id: site.default_bank_id,
        proforma_progressive: site.proforma_progressive,
        invoice_progressive: site.invoice_progressive,
      }
    }
    return {
      name: '',
      email: '',
      fiscal_code: '',
      vat_number: '',
      phone: '',
      pec: '',
      fax: '',
      notes: '',
      address: EMPTY_ADDRESS,
      responsible_rda_id: null,
      responsible_tickets_id: null,
      responsible_validation_contracts_id: null,
      responsible_validation_contracts_two_id: null,
      default_bank_id: null,
      proforma_progressive: null,
      invoice_progressive: null,
    }
  }, [mode])

  // EDIT: pre-known {id, label} for the responsible selects (AC-016/AC-017), so
  // each picker shows its label immediately without an extra hydration fetch.
  const responsibleItem = (ref: { id: number; label: string } | null): ForSelectItem | null =>
    ref ? { id: ref.id, label: ref.label } : null

  const selectedResponsibleRdaItem = useMemo(
    () => (mode.type === 'edit' ? responsibleItem(mode.companySite.responsible_rda) : null),
    [mode],
  )
  const selectedResponsibleTicketsItem = useMemo(
    () => (mode.type === 'edit' ? responsibleItem(mode.companySite.responsible_tickets) : null),
    [mode],
  )
  const selectedResponsibleValidationContractsItem = useMemo(
    () =>
      mode.type === 'edit'
        ? responsibleItem(mode.companySite.responsible_validation_contracts)
        : null,
    [mode],
  )
  const selectedResponsibleValidationContractsTwoItem = useMemo(
    () =>
      mode.type === 'edit'
        ? responsibleItem(mode.companySite.responsible_validation_contracts_two)
        : null,
    [mode],
  )

  const form = useForm<CompanySiteFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: CompanySiteFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateCompanySite(
          mode.companySite.id,
          buildUpdatePayload(values, mode.companySite, banksDraft),
        )
        toast.success(t('companySites.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCompanySite(buildCreatePayload(values, banksDraft))
      toast.success(t('companySites.form.created'))

      // The site exists now; upload the deferred logo before handing off. A
      // failed logo upload must not lose the created site — surface a toast
      // and proceed, returning the freshest resource we have.
      if (pendingLogo) {
        try {
          const withLogo = await uploadCompanySiteLogo(created.id, pendingLogo)
          onSuccess(withLogo)
          return
        } catch {
          toast.error(t('avatar.avatarUploadError'))
        }
      }

      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('companySites.form.genericError'))
      }
    }
  }

  // EDIT mode: logo actions hit the backend immediately and refresh the
  // cached detail so the form (and any open detail view) reflects the change.
  const handleLogoUpload = async (file: File) => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await uploadCompanySiteLogo(mode.companySite.id, file)
    queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
    onSiteChange?.()
  }

  const handleLogoRemove = async () => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await deleteCompanySiteLogo(mode.companySite.id)
    queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
    onSiteChange?.()
  }

  // EDIT mode only: sets this site as the company's default one (AC-020). The
  // affordance itself is gated by the caller on `!is_default`.
  const [settingDefault, setSettingDefault] = useState(false)
  const handleSetDefault = async () => {
    if (mode.type !== 'edit') {
      return
    }
    setSettingDefault(true)
    try {
      const updated = await setDefaultCompanySite(mode.companySite.id)
      queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
      toast.success(t('companySites.form.defaultSet'))
      onSiteChange?.()
    } catch {
      toast.error(t('companySites.form.defaultError'))
    } finally {
      setSettingDefault(false)
    }
  }

  return {
    form,
    isEdit,
    serverError,
    pendingLogo,
    setPendingLogo,
    banksDraft,
    setBanksDraft,
    selectedResponsibleRdaItem,
    selectedResponsibleTicketsItem,
    selectedResponsibleValidationContractsItem,
    selectedResponsibleValidationContractsTwoItem,
    onSubmit,
    handleLogoUpload,
    handleLogoRemove,
    canUploadLogo: canAction('upload_logo'),
    canRemoveLogo: canAction('delete_logo'),
    // Set-default (AC-020): visible only in edit mode, on a non-default site,
    // gated by the resolved `set_default` action permission.
    canSetDefault: isEdit && !mode.companySite.is_default && canAction('set_default'),
    settingDefault,
    handleSetDefault,
  }
}
