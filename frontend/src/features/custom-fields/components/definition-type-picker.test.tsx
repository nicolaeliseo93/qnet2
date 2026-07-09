import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { DefinitionTypePicker } from '@/features/custom-fields/components/definition-type-picker'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { ResourcePermissions } from '@/features/authorization/types'

const FULL_ACCESS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function Harness() {
  const form = useForm<CustomFieldDefinitionFormValues>({ defaultValues: { type: 'text' } })
  return (
    <Form {...form}>
      <ResourcePermissionsProvider permissions={FULL_ACCESS}>
        <DefinitionTypePicker control={form.control} lockIdentity={false} />
      </ResourcePermissionsProvider>
    </Form>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('DefinitionTypePicker', () => {
  it('explains the default text type with a description and an example', () => {
    render(<Harness />)
    expect(screen.getByText('A short single line of text.')).toBeInTheDocument()
    expect(screen.getByText('Customer code, plate, serial number.')).toBeInTheDocument()
    expect(screen.getByText('Example:')).toBeInTheDocument()
  })

  it('updates the explainer when another type is selected', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'Date' }))

    expect(screen.getByText('A calendar date.')).toBeInTheDocument()
    expect(screen.getByText('Contract expiry: 2026-07-09.')).toBeInTheDocument()
  })
})
