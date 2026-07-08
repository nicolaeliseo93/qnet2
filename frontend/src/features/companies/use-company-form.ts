import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
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
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { customFieldErrorPaths } from '@/features/custom-fields/custom-fields-errors'
import {
  isCustomFieldDescriptor,
  type CustomFieldDescriptor,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

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

/** No custom fields resolved yet (loading) or genuinely none defined for the resource. */
const EMPTY_CUSTOM_FIELD_DESCRIPTORS: CustomFieldDescriptor[] = []

/** A brand-new company (or one loaded before any custom field had a value) starts with none set. */
const EMPTY_CUSTOM_FIELD_VALUES: Record<string, CustomFieldValue> = {}

/** `buildCustomFieldsSchema` takes a full `ResourcePermissions`; only `.fields` is consulted here. */
const CUSTOM_FIELDS_RESOURCE_STUB: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

interface UseCompanyFormArgs {
  mode: CompanyFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (company: CompanyDetail) => void
}

/**
 * Owns every non-render concern of `CompanyForm`: RHF/Zod wiring, default
 * values (including the single embedded address block and any custom
 * fields), server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 *
 * Custom fields (spec 0021, pilot rollout): reads `/meta/companies` via the
 * SAME `useResourceMeta` query the mounted `<CustomFieldsSection>` reads from
 * (deduped by TanStack Query, one request) purely to build the dynamic Zod
 * schema and the server-error paths — the section itself owns rendering.
 */
export function useCompanyForm({ mode, onSuccess }: UseCompanyFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const metaQuery = useResourceMeta('companies')
  const { field: fieldPermission } = useResourcePermissions()

  const customFieldDescriptors = useMemo(
    () => metaQuery.data?.fields.filter(isCustomFieldDescriptor) ?? EMPTY_CUSTOM_FIELD_DESCRIPTORS,
    [metaQuery.data],
  )

  // Step 1: resolve the per-field permissions for the custom fields from the
  // active `ResourcePermissionsProvider` scope (edit: instance detail;
  // create: create-context meta) — the same source `<CustomFieldsSection>` reads.
  const customFieldsPermissions = useMemo<ResourcePermissions>(
    () => ({
      resource: CUSTOM_FIELDS_RESOURCE_STUB,
      actions: {},
      fields: Object.fromEntries(
        customFieldDescriptors.map((descriptor) => [descriptor.key, fieldPermission(descriptor.key)]),
      ),
    }),
    [customFieldDescriptors, fieldPermission],
  )

  // Step 2: build the dynamic custom-fields schema, then merge it into the
  // create/edit company schema under the `custom_fields` key.
  const customFieldsSchema = useMemo(
    () => buildCustomFieldsSchema(customFieldDescriptors, customFieldsPermissions, t),
    [customFieldDescriptors, customFieldsPermissions, t],
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCompanySchema(t, customFieldsSchema)
        : buildCreateCompanySchema(t, customFieldsSchema),
    [isEdit, t, customFieldsSchema],
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
        custom_fields: mode.company.custom_fields ?? EMPTY_CUSTOM_FIELD_VALUES,
      }
    }
    return {
      denomination: '',
      vat_number: '',
      address: EMPTY_ADDRESS,
      custom_fields: EMPTY_CUSTOM_FIELD_VALUES,
    }
  }, [mode])

  const form = useForm<CompanyFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: CompanyFormValues) => {
    setServerError(null)
    const errorFields: Path<CompanyFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...customFieldErrorPaths<CompanyFormValues>(customFieldDescriptors),
    ]
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
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('companies.form.genericError'))
      }
    }
  }

  return { form, isEdit, serverError, onSubmit }
}
