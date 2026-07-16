import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import {
  buildCreateStatusGroupSchema,
  buildUpdateStatusGroupSchema,
} from '@/features/status-groups/status-group-schema'
import type { ResourcePermissions } from '@/features/authorization/types'

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** No custom fields defined for these assertions: a real, but empty, toolbox schema. */
function emptyCustomFieldsSchema() {
  return buildCustomFieldsSchema([], FULL_ACCESS_PERMISSIONS, i18n.t)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateStatusGroupSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Open', color: 'blue', sort_order: 1, custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: '', color: '', sort_order: 0, custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({
      name: 'a'.repeat(192),
      color: '',
      sort_order: 0,
      custom_fields: {},
    })
    expect(result.success).toBe(false)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Open', color: '', sort_order: 0, custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it('rejects a negative sort_order', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Open', color: '', sort_order: -1, custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a non-integer sort_order', () => {
    const schema = buildCreateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Open', color: '', sort_order: 1.5, custom_fields: {} })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateStatusGroupSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateStatusGroupSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Closed', color: 'green', sort_order: 2, custom_fields: {} })
    expect(result.success).toBe(true)
  })
})
