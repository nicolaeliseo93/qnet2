import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useEnumOptions } from '@/features/config/use-config'
import {
  buildContactSchema,
  type ContactFormValues,
} from '@/features/personal-data/contact-schema'
import type { ContactDraft } from '@/features/personal-data/types'

interface ContactFormProps {
  /** When present the form edits this draft; otherwise it creates a new one. */
  contact?: ContactDraft
  /** Returns the validated draft fields to the manager's buffer. */
  onSubmit: (fields: Omit<ContactDraft, '_key'>) => void
  /** Called when the user cancels. */
  onCancel: () => void
}

/**
 * Inline create/edit form for a single contact channel. Controlled/buffered: it
 * validates per-type (mirroring the backend) and hands the values back through
 * `onSubmit`; it performs no network call. Owner-agnostic.
 */
export function ContactForm({ contact, onSubmit, onCancel }: ContactFormProps) {
  const { t } = useTranslation()
  const typeOptions = useEnumOptions('contact_type')
  const schema = useMemo(() => buildContactSchema(t), [t])

  const defaultType =
    contact?.type ??
    typeOptions.find((option) => option.is_default)?.value ??
    typeOptions[0]?.value ??
    ''

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      type: defaultType,
      value: contact?.value ?? '',
      label: contact?.label ?? '',
      is_primary: contact?.is_primary ?? false,
    },
  })

  const handleSubmit = (values: ContactFormValues) => {
    onSubmit({
      ...(contact?.id !== undefined ? { id: contact.id } : {}),
      type: values.type,
      value: values.value,
      label: values.label || null,
      is_primary: values.is_primary,
    })
  }

  return (
    <Form {...form}>
      {/* A div, not a form: this inline editor is buffered and rendered inside the
          outer user form, so it must not nest a <form>. Save validates and commits
          to the parent buffer via RHF's handleSubmit on a plain button. */}
      <div className="flex flex-col gap-3 rounded-md border bg-muted/30 p-3">
        <FormField
          control={form.control}
          name="type"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('personalData.contacts.type')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {typeOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="value"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('personalData.contacts.value')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="label"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('personalData.contacts.label')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="is_primary"
          render={({ field }) => (
            <FormItem>
              <label className="flex items-center gap-2 text-sm font-normal">
                <input
                  type="checkbox"
                  className="size-4 accent-primary"
                  checked={field.value}
                  onChange={(event) => field.onChange(event.target.checked)}
                />
                {t('personalData.contacts.primary')}
              </label>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
            {t('personalData.contacts.cancel')}
          </Button>
          <Button
            type="button"
            size="sm"
            onClick={form.handleSubmit(handleSubmit)}
          >
            {t('personalData.contacts.save')}
          </Button>
        </div>
      </div>
    </Form>
  )
}
