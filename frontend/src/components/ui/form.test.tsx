import { render, screen } from '@testing-library/react'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { describe, expect, it } from 'vitest'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'

function RequiredLabelFixture({ required }: { required?: boolean }) {
  const form = useForm<{ name: string }>({ defaultValues: { name: '' } })

  return (
    <Form {...form}>
      <FormField
        control={form.control}
        name="name"
        render={({ field }) => (
          <FormItem>
            <FormLabel required={required}>Name</FormLabel>
            <FormControl>
              <Input {...field} />
            </FormControl>
          </FormItem>
        )}
      />
    </Form>
  )
}

describe('FormLabel', () => {
  it('renders a red asterisk when the field is required', () => {
    render(<RequiredLabelFixture required />)

    expect(screen.getByText('*')).toBeInTheDocument()
  })

  it('does not render an asterisk when the field is optional', () => {
    render(<RequiredLabelFixture />)

    expect(screen.queryByText('*')).not.toBeInTheDocument()
  })
})

function ValidationErrorFixture() {
  const form = useForm<{ name: string }>({ defaultValues: { name: '' } })

  useEffect(() => {
    form.trigger('name')
  }, [form])

  return (
    <Form {...form}>
      <FormField
        control={form.control}
        name="name"
        rules={{ required: 'Name is required' }}
        render={({ field }) => (
          <FormItem>
            <FormLabel>Name</FormLabel>
            <FormControl>
              <Input {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </Form>
  )
}

function NeutralMessageFixture() {
  const form = useForm<{ name: string }>({ defaultValues: { name: '' } })

  return (
    <Form {...form}>
      <FormField
        control={form.control}
        name="name"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Name</FormLabel>
            <FormControl>
              <Input {...field} />
            </FormControl>
            <FormMessage>Optional helper text</FormMessage>
          </FormItem>
        )}
      />
    </Form>
  )
}

describe('FormMessage', () => {
  it('exposes role="alert" so screen readers announce the validation error', async () => {
    render(<ValidationErrorFixture />)

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Name is required'
    )
  })

  it('does not expose role="alert" for a neutral, non-error message', () => {
    render(<NeutralMessageFixture />)

    expect(screen.getByText('Optional helper text')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
