import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { describe, expect, it } from 'vitest'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
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
