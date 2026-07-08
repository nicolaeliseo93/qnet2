import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import { customFields as enCustomFields } from '@/i18n/locales/en-custom-fields'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/** Spec 0021 AC-023: required/min/max/regex enforced with localized messages. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { customFields: enCustomFields }, true, true)
})

function permission(overrides: Partial<FieldPermission> = {}): FieldPermission {
  return {
    visible: true,
    hidden: false,
    editable: true,
    readonly: false,
    required: false,
    disabled: false,
    ...overrides,
  }
}

function permissionsFor(fields: Record<string, FieldPermission>): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields,
    actions: {},
  }
}

const CODE_FIELD: CustomFieldDescriptor = {
  key: 'custom.code',
  type: 'text',
  label: 'Code',
  group: null,
  mandatory: false,
  source: 'custom',
  config: { minLength: 3, maxLength: 5, regex: '^[A-Z]+$' },
}

const AGE_FIELD: CustomFieldDescriptor = {
  key: 'custom.age',
  type: 'integer',
  label: 'Age',
  group: null,
  mandatory: false,
  source: 'custom',
  config: { min: 18, max: 65 },
}

const TIER_FIELD: CustomFieldDescriptor = {
  key: 'custom.tier',
  type: 'enum',
  label: 'Tier',
  group: null,
  mandatory: false,
  source: 'custom',
  options: [{ value: 'gold', label: 'Gold' }, { value: 'silver', label: 'Silver' }],
}

describe('buildCustomFieldsSchema', () => {
  it('rejects a missing value for a field required via the role permission', () => {
    const schema = buildCustomFieldsSchema(
      [CODE_FIELD],
      permissionsFor({ 'custom.code': permission({ required: true }) }),
      i18n.t,
    )
    const result = schema.safeParse({ code: null })
    expect(result.success).toBe(false)
    expect(result.error?.issues[0]?.message).toBe('This field is required.')
  })

  it('rejects a missing value for a field that is mandatory regardless of role', () => {
    const schema = buildCustomFieldsSchema(
      [{ ...CODE_FIELD, mandatory: true }],
      permissionsFor({ 'custom.code': permission({ required: false }) }),
      i18n.t,
    )
    const result = schema.safeParse({ code: '' })
    expect(result.success).toBe(false)
  })

  it('accepts a value satisfying min/max length and the regex', () => {
    const schema = buildCustomFieldsSchema([CODE_FIELD], permissionsFor({}), i18n.t)
    expect(schema.safeParse({ code: 'ABCD' }).success).toBe(true)
  })

  it('rejects a value shorter than minLength with the localized message', () => {
    const schema = buildCustomFieldsSchema([CODE_FIELD], permissionsFor({}), i18n.t)
    const result = schema.safeParse({ code: 'AB' })
    expect(result.success).toBe(false)
    expect(result.error?.issues[0]?.message).toBe('Must be at least 3 characters.')
  })

  it('rejects a value longer than maxLength', () => {
    const schema = buildCustomFieldsSchema([CODE_FIELD], permissionsFor({}), i18n.t)
    expect(schema.safeParse({ code: 'ABCDEF' }).success).toBe(false)
  })

  it('rejects a value not matching the regex', () => {
    const schema = buildCustomFieldsSchema([CODE_FIELD], permissionsFor({}), i18n.t)
    const result = schema.safeParse({ code: 'abcd' })
    expect(result.success).toBe(false)
    expect(result.error?.issues[0]?.message).toBe('This value does not match the expected format.')
  })

  it('does not apply length/regex rules to an empty optional value', () => {
    const schema = buildCustomFieldsSchema([CODE_FIELD], permissionsFor({}), i18n.t)
    expect(schema.safeParse({ code: null }).success).toBe(true)
  })

  it('enforces numeric min/max', () => {
    const schema = buildCustomFieldsSchema([AGE_FIELD], permissionsFor({}), i18n.t)
    expect(schema.safeParse({ age: 17 }).success).toBe(false)
    expect(schema.safeParse({ age: 66 }).success).toBe(false)
    expect(schema.safeParse({ age: 30 }).success).toBe(true)
  })

  it('rejects an enum value outside the descriptor options', () => {
    const schema = buildCustomFieldsSchema([TIER_FIELD], permissionsFor({}), i18n.t)
    expect(schema.safeParse({ tier: 'platinum' }).success).toBe(false)
    expect(schema.safeParse({ tier: 'gold' }).success).toBe(true)
  })
})
