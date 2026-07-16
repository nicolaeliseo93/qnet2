import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import {
  buildCreatePipelineStatusSchema,
  buildUpdatePipelineStatusSchema,
} from '@/features/pipeline-statuses/pipeline-status-schema'
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

describe('buildCreatePipelineStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({
      name: 'Draft',
      color: 'blue',
      group: 'open',
      custom_fields: {},
    })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: '', color: '', group: 'open', custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({
      name: 'a'.repeat(192),
      color: '',
      group: 'open',
      custom_fields: {},
    })
    expect(result.success).toBe(false)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', group: 'open', custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it.each(['open', 'pending', 'closed'] as const)('accepts the group value "%s"', (group) => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', group, custom_fields: {} })
    expect(result.success).toBe(true)
  })

  it('rejects a group value outside the fixed enum', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', group: 'archived', custom_fields: {} })
    expect(result.success).toBe(false)
  })

  it('rejects a missing group (required on create)', () => {
    const schema = buildCreatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({ name: 'Draft', color: '', custom_fields: {} })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdatePipelineStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdatePipelineStatusSchema(i18n.t, emptyCustomFieldsSchema())
    const result = schema.safeParse({
      name: 'Active',
      color: 'green',
      group: 'closed',
      custom_fields: {},
    })
    expect(result.success).toBe(true)
  })
})
