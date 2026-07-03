import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createCompany, updateCompany } from '@/features/companies/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/companies/company-form-payload'
import {
  buildCreateCompanySchema,
  buildUpdateCompanySchema,
  type CreateCompanyFormValues,
  type UpdateCompanyFormValues,
} from '@/features/companies/company-schema'
import type { CompanyDetail } from '@/features/companies/types'
import type { CompanyFormMode } from '@/features/companies/company-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'denomination',
  'vat_number',
  'address.line1',
  'address.line2',
  'address.postal_code',
  'address.country_id',
  'address.state_id',
  'address.province_id',
  'address.city_id',
] as const

export type CompanyFormValues = CreateCompanyFormValues & UpdateCompanyFormValues

/** The blank address block a new company (or one without an address) starts from. */
const EMPTY_ADDRESS: CompanyFormValues['address'] = {
  line1: '',
  line2: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

interface UseCompanyFormArgs {
  mode: CompanyFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (company: CompanyDetail) => void
}

/**
 * Owns every non-render concern of `CompanyForm`: RHF/Zod wiring, default
 * values (including the single embedded address block), server 422 mapping
 * and the create/update submit. The component stays UI-only; this hook is
 * the orchestration point (`onSubmit`).
 */
export function useCompanyForm({ mode, onSuccess }: UseCompanyFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateCompanySchema(t) : buildCreateCompanySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<CompanyFormValues>(() => {
    if (mode.type === 'edit') {
      const address = mode.company.address
      return {
        denomination: mode.company.denomination,
        vat_number: mode.company.vat_number ?? '',
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
      }
    }
    return { denomination: '', vat_number: '', address: EMPTY_ADDRESS }
  }, [mode])

  const form = useForm<CompanyFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: CompanyFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateCompany(
          mode.company.id,
          buildUpdatePayload(values, mode.company),
        )
        toast.success(t('companies.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCompany(buildCreatePayload(values))
      toast.success(t('companies.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('companies.form.genericError'))
      }
    }
  }

  return { form, isEdit, serverError, onSubmit }
}
