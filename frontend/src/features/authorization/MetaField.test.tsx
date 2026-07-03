import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form, FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { ResourcePermissions } from '@/features/authorization/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

interface Values {
  email: string
  roles: string
  bonus: string
}

function permissions(): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: true,
      update: true,
      delete: true,
      export: true,
      import: true,
    },
    fields: {
      email: {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: true,
        disabled: false,
      },
      roles: {
        visible: true,
        hidden: false,
        editable: false,
        readonly: true,
        required: false,
        disabled: false,
      },
      secret: {
        visible: false,
        hidden: true,
        editable: false,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
  }
}

function Harness({ perms }: { perms: ResourcePermissions }) {
  const form = useForm<Values>({
    defaultValues: { email: 'ada@example.com', roles: 'admin', bonus: 'x' },
  })

  return (
    <ResourcePermissionsProvider permissions={perms}>
      <Form {...form}>
        <form>
          <MetaField control={form.control} name="email" metaKey="email" label="Email">
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input {...field} disabled={disabled} readOnly={readOnly} />
              </FormControl>
            )}
          </MetaField>
          <MetaField control={form.control} name="roles" metaKey="roles" label="Roles">
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input {...field} disabled={disabled} readOnly={readOnly} />
              </FormControl>
            )}
          </MetaField>
          <MetaField control={form.control} name="bonus" metaKey="secret" label="Secret">
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input {...field} disabled={disabled} readOnly={readOnly} />
              </FormControl>
            )}
          </MetaField>
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}

describe('MetaField', () => {
  it('renders a visible+editable field enabled, with the required marker', () => {
    render(<Harness perms={permissions()} />)
    const email = screen.getByLabelText(/Email/)
    expect(email).toBeEnabled()
    expect(screen.getByText('*')).toBeInTheDocument()
  })

  it('renders a readonly/non-editable field disabled and un-editable', () => {
    render(<Harness perms={permissions()} />)
    const roles = screen.getByLabelText('Roles')
    expect(roles).toBeDisabled()
    expect(roles).toHaveAttribute('readonly')
  })

  it('does not render a hidden field at all', () => {
    render(<Harness perms={permissions()} />)
    expect(screen.queryByLabelText('Secret')).not.toBeInTheDocument()
    expect(screen.queryByText('Secret')).not.toBeInTheDocument()
  })

  it('falls back to visible+editable when a field is missing from metadata', () => {
    const perms = permissions()
    delete (perms.fields as Record<string, unknown>).email
    render(<Harness perms={perms} />)
    expect(screen.getByLabelText(/Email/)).toBeEnabled()
  })
})
