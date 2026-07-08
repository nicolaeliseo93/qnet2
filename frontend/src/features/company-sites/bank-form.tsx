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
import { buildBankSchema, type BankFormValues } from '@/features/company-sites/bank-schema'
import type { BankDraft } from '@/features/company-sites/types'

interface BankFormProps {
  /** When present the form edits this draft; otherwise it creates a new one. */
  bank?: BankDraft
  /** Returns the validated draft fields to the manager. */
  onSubmit: (fields: Omit<BankDraft, '_key'>) => void
  /** Called when the user cancels. */
  onCancel: () => void
}

/**
 * Create/edit form for a single bank row, rendered inside the manager's
 * dialog. Mirrors `ContactForm`: validates client-side (mirroring the backend
 * rules) and hands the values back through `onSubmit`; the manager decides
 * how to merge them into the buffer.
 */
export function BankForm({ bank, onSubmit, onCancel }: BankFormProps) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildBankSchema(t), [t])

  const form = useForm<BankFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: bank?.name ?? '',
      iban: bank?.iban ?? '',
      notes: bank?.notes ?? '',
      is_primary: bank?.is_primary ?? false,
    },
  })

  const handleSubmit = (values: BankFormValues) => {
    onSubmit({
      ...(bank?.id !== undefined ? { id: bank.id } : {}),
      name: values.name,
      iban: values.iban || null,
      notes: values.notes || null,
      is_primary: values.is_primary,
    })
  }

  return (
    <Form {...form}>
      <div className="flex flex-col gap-3">
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('companySites.form.banks.name')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="iban"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('companySites.form.banks.iban')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="notes"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('companySites.form.banks.notes')}</FormLabel>
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
                {t('companySites.form.banks.preferred')}
              </label>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
            {t('companySites.form.banks.cancel')}
          </Button>
          <Button type="button" size="sm" onClick={form.handleSubmit(handleSubmit)}>
            {t('companySites.form.banks.save')}
          </Button>
        </div>
      </div>
    </Form>
  )
}
