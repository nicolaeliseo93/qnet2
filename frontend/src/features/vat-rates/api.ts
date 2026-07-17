import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateVatRatePayload,
  UpdateVatRatePayload,
  VatRateDetail,
  VatRateDetailWithPermissions,
} from '@/features/vat-rates/types'

/**
 * Normalizes a VAT rate detail from the wire: Laravel's `decimal:2` cast
 * serializes `rate` as a string (e.g. "22.00"), but the domain treats it as a
 * number (form validation, display). Coercing here keeps `VatRateDetail.rate`
 * honest so the plain `z.number()` resolver accepts an untouched edit value.
 */
function normalizeVatRate(detail: VatRateDetail): VatRateDetail {
  return { ...detail, rate: Number(detail.rate) }
}

/**
 * Fetches a single VAT rate detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchVatRate(id: number): Promise<VatRateDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<VatRateDetail, ResourcePermissions>
  >(`/vat-rates/${id}`)
  return { ...normalizeVatRate(data.data), permissions: data.permissions }
}

/** Creates a VAT rate. Returns the created resource from the envelope `data`. */
export async function createVatRate(payload: CreateVatRatePayload): Promise<VatRateDetail> {
  const { data } = await apiClient.post<ApiResponse<VatRateDetail>>('/vat-rates', payload)
  return normalizeVatRate(data.data)
}

/** Partially updates a VAT rate (PATCH). Returns the updated resource. */
export async function updateVatRate(
  id: number,
  payload: UpdateVatRatePayload,
): Promise<VatRateDetail> {
  const { data } = await apiClient.patch<ApiResponse<VatRateDetail>>(`/vat-rates/${id}`, payload)
  return normalizeVatRate(data.data)
}

/** Deletes a VAT rate. Backend responds 204 with no body. */
export async function deleteVatRate(id: number): Promise<void> {
  await apiClient.delete(`/vat-rates/${id}`)
}
