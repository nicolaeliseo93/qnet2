import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schemas for the user create/edit form, built as factories so validation
 * messages are localized via the i18n `t` function (same pattern as the auth
 * forms). The shapes mirror the frozen backend contract 1:1.
 */

/** Minimum password length enforced client-side as an affordance. */
const PASSWORD_MIN_LENGTH = 8

const localeSchema = z.enum(['en', 'it'])

/**
 * Shared scalar fields common to create and edit. The user's display `name` is
 * NOT here: it is derived server-side from the personal-data card (single source
 * of truth), never entered on the user form.
 */
function baseFields(t: TFunction) {
  return {
    email: z
      .string()
      .min(1, t('users.form.emailRequired'))
      .email(t('users.form.emailInvalid')),
    locale: localeSchema,
    // Role IDS (for-select standard, ADR 0011): the picker submits ids.
    roles: z.array(z.number()),
  }
}

/**
 * Create schema: password is required and must match its confirmation.
 */
export function buildCreateUserSchema(t: TFunction) {
  return z
    .object({
      ...baseFields(t),
      password: z.string().min(PASSWORD_MIN_LENGTH, t('users.form.passwordMinLength')),
      password_confirmation: z.string().min(1, t('users.form.confirmPasswordRequired')),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ['password_confirmation'],
      message: t('users.form.passwordsDontMatch'),
    })
}

/**
 * Edit schema: password is optional (PATCH). When provided it must satisfy the
 * length rule and match its confirmation; when left blank both fields are empty
 * and ignored by the caller before sending the PATCH.
 */
export function buildUpdateUserSchema(t: TFunction) {
  return z
    .object({
      ...baseFields(t),
      password: z
        .string()
        .refine((value) => value === '' || value.length >= PASSWORD_MIN_LENGTH, {
          message: t('users.form.passwordMinLength'),
        }),
      password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ['password_confirmation'],
      message: t('users.form.passwordsDontMatch'),
    })
}

export type CreateUserFormValues = z.infer<ReturnType<typeof buildCreateUserSchema>>
export type UpdateUserFormValues = z.infer<ReturnType<typeof buildUpdateUserSchema>>
