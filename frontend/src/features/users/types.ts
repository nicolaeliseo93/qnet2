/**
 * Users CRUD types. The generic table types (columns/filters/actions/rows) live
 * in `features/table/types.ts`; this file holds only what is genuinely
 * users-specific — the user resource and its create/update payloads.
 * Source of truth: the frozen Users CRUD API contract.
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Allowed UI/user locales. Kept as a defensive guard only — the selectable
 * locales are served by the backend (GET /api/config → enums.locale) and the
 * form renders them from there, never from this literal.
 */
export type UserLocale = 'en' | 'it'

/** A role membership as returned by the backend ({id, name}). */
export interface UserRole {
  id: number
  name: string
}

/** Employment relationship with the organization (spec 0015). */
export const RELATIONSHIP_TYPES = ['employee', 'self_employed', 'other'] as const
export type RelationshipType = (typeof RELATIONSHIP_TYPES)[number]

/** Employment qualification/role bucket, used for cost/payroll categorization (spec 0015). */
export const QUALIFICATION_TYPES = [
  'employee_level_5',
  'administrative',
  'coordinator',
  'iso_consultant',
  'teacher_cococo',
  'teacher_vat',
  'trainee_cost',
  'hourly_cost_me',
] as const
export type QualificationType = (typeof QUALIFICATION_TYPES)[number]

/** A related entity reference as returned inside `employment` ({id, label}). */
export interface EmploymentRelationRef {
  id: number
  label: string
  subtitle?: string | null
}

/**
 * The user's employment profile (spec 0015), present only when the backend
 * loaded it. `standard_daily_minutes`/`break_daily_minutes` are stored as
 * plain minute counts (0..1440), not a TIME column.
 */
export interface EmploymentDetail {
  id: number
  is_manager: boolean
  job_description: string | null
  relationship_type: RelationshipType | null
  qualification_type: QualificationType | null
  hired_at: string | null
  terminated_at: string | null
  standard_daily_minutes: number | null
  break_daily_minutes: number | null
  reports_to_id: number | null
  business_function_id: number | null
  company_id: number | null
  operational_site_id: number | null
  reports_to: EmploymentRelationRef | null
  business_function: EmploymentRelationRef | null
  company: EmploymentRelationRef | null
  operational_site: EmploymentRelationRef | null
}

/**
 * Single user detail returned by GET/POST/PATCH /users (envelope `data`).
 * Matches UserResource in the frozen API contract.
 */
export interface UserDetail {
  id: number
  name: string
  email: string
  locale: UserLocale
  /** Role memberships as {id, name}: the form picks by id, the detail shows name. */
  roles: UserRole[]
  /** Absolute URL to the authenticated avatar download endpoint, or null. */
  avatar_url: string | null
  /**
   * The user's personal-data card (with its contacts and addresses), present only
   * when the backend loaded it (whenLoaded). Absent/null when the user has none.
   */
  personal_data?: PersonalDataCard | null
  /** The user's employment profile (spec 0015), null when the user has none. */
  employment?: EmploymentDetail | null
  created_at: string | null
}

/**
 * A `UserDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /users/{user}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface UserDetailWithPermissions extends UserDetail {
  permissions: ResourcePermissions
}

/**
 * The nested `employment` object accepted by the user write endpoints
 * (spec 0015), written atomically with the user in the same request. Always
 * built and sent from the form (upsert semantics), mirroring `personal_data`.
 */
export interface EmploymentPayload {
  is_manager: boolean
  job_description: string | null
  reports_to_id: number | null
  business_function_id: number | null
  relationship_type: RelationshipType | null
  company_id: number | null
  operational_site_id: number | null
  qualification_type: QualificationType | null
  hired_at: string | null
  terminated_at: string | null
  standard_daily_minutes: number | null
  break_daily_minutes: number | null
}

/** Payload for POST /users (create). `password` is required here. */
export interface CreateUserPayload {
  email: string
  locale: UserLocale
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password: string
  password_confirmation: string
  /**
   * The nested personal-data card written atomically with the user (ADR 0012).
   * Required: `users.name` is derived server-side from this card (the user form
   * has no `name` field), so identity must always be present on create.
   */
  personal_data: PersonalDataPayload
  /** The nested employment profile written atomically with the user (spec 0015). */
  employment: EmploymentPayload
}

/**
 * Payload for PATCH /users/{id} (partial update). Every field is optional so
 * the request only carries what actually changed.
 */
export interface UpdateUserPayload {
  email?: string
  locale?: UserLocale
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password?: string
  password_confirmation?: string
  /**
   * Optional nested profile written atomically with the user (ADR 0012). Omit to
   * leave the card untouched; present = card upsert plus authoritative child sync.
   */
  personal_data?: PersonalDataPayload
  /** The nested employment profile written atomically with the user (spec 0015). */
  employment?: EmploymentPayload
}
