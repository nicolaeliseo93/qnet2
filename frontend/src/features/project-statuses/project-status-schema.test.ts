import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import {
  buildCreateProjectStatusSchema,
  buildUpdateProjectStatusSchema,
} from '@/features/project-statuses/project-status-schema'
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

describe('buildCreateProjectStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: 'blue', sort_order: 1, custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: '', color: '', sort_order: 0, custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({
      name: 'a'.repeat(192),
      color: '',
      sort_order: 0,
      custom_fields: {},
    })
    expect(result.success).toBe(false)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', sort_order: 0, custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it('rejects a negative sort_order', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', sort_order: -1, custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a non-integer sort_order', () => {
    const schema = buildCreateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', sort_order: 1.5, custom_fields: {} })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateProjectStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateProjectStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Active', color: 'green', sort_order: 2, custom_fields: {} })
    expect(result.success).toBe(true)
  })
})
