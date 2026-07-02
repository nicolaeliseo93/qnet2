import { useEffect, useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
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
  buildPersonalDataSchema,
  type PersonalDataFormValues,
} from '@/features/personal-data/personal-data-schema'
import type { PersonalDataDraft } from '@/features/personal-data/types'

/** Sentinel for the "no title" option (Radix Select items need a non-empty value). */
const TITLE_NONE = '__none__'

interface PersonalDataCardFormProps {
  /** The buffered card fields owned by the parent section. */
  value: PersonalDataDraft
  /** Emits the next card draft (fields only; children are managed separately). */
  onChange: (next: PersonalDataDraft) => void
}

/**
 * Controlled/buffered create/edit form for the registry card fields. The `type`
 * and `title` options come from the server config; individual vs company fields
 * toggle by the selected type, mirroring the backend per-type contract. It keeps
 * RHF + Zod for inline validation but performs no network call: every change is
 * lifted into the parent buffer through `onChange`, and the whole tree is saved by
 * the surrounding user form (ADR 0012).
 */
export function PersonalDataCardForm({
  value,
  onChange,
}: PersonalDataCardFormProps) {
  const { t } = useTranslation()
  const typeOptions = useEnumOptions('personal_data_type')
  const titleOptions = useEnumOptions('personal_title')
  const schema = useMemo(() => buildPersonalDataSchema(t), [t])

  const form = useForm<PersonalDataFormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      type: value.type,
      title: value.title ?? '',
      first_name: value.first_name ?? '',
      last_name: value.last_name ?? '',
      company_name: value.company_name ?? '',
      tax_code: value.tax_code ?? '',
      vat_number: value.vat_number ?? '',
      sdi_code: value.sdi_code ?? '',
      birth_date: value.birth_date ?? '',
    },
  })

  // Watch every field so edits flow into the parent buffer. `useWatch` re-renders
  // this component on each change; `isCompany` toggles which fields render.
  const watched = useWatch({ control: form.control })
  const isCompany = watched.type === 'company'

  // Mirror the current field values into the parent buffer (in an effect, so the
  // parent update happens after this render rather than during it), preserving the
  // draft's id and its children. The no-op compare prevents an update loop.
  const next: PersonalDataDraft = {
    ...(value.id !== undefined ? { id: value.id } : {}),
    type: (watched.type ?? value.type) as PersonalDataDraft['type'],
    title: watched.title || null,
    first_name: watched.first_name || null,
    last_name: watched.last_name || null,
    company_name: watched.company_name || null,
    tax_code: watched.tax_code || null,
    vat_number: watched.vat_number || null,
    sdi_code: watched.sdi_code || null,
    birth_date: watched.birth_date || null,
    contacts: value.contacts,
    addresses: value.addresses,
  }
  const changed = !sameCardFields(next, value)
  useEffect(() => {
    if (changed) {
      onChange(next)
    }
    // `next` is recomputed each render from `watched`; gating on `changed` keeps
    // this to a single parent update per actual field change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [changed])

  return (
    <Form {...form}>
      {/* A div, not a form: this card is buffered and lives inside the user
          form, so it must never nest a <form> nor own a submit. */}
      <div className="flex flex-col gap-3">
        <div className="grid grid-cols-2 gap-3">
          <FormField
            control={form.control}
            name="type"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('personalData.form.type')}</FormLabel>
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
            name="title"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.form.title')}</FormLabel>
                <Select
                  value={field.value ? field.value : TITLE_NONE}
                  onValueChange={(value) =>
                    field.onChange(value === TITLE_NONE ? '' : value)
                  }
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value={TITLE_NONE}>
                      {t('personalData.form.titleNone')}
                    </SelectItem>
                    {titleOptions.map((option) => (
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
        </div>

        {isCompany ? (
          <FormField
            control={form.control}
            name="company_name"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('personalData.form.companyName')}</FormLabel>
                <FormControl>
                  <Input autoComplete="organization" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : (
          <div className="grid grid-cols-2 gap-3">
            <FormField
              control={form.control}
              name="first_name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('personalData.form.firstName')}</FormLabel>
                  <FormControl>
                    <Input autoComplete="given-name" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="last_name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('personalData.form.lastName')}</FormLabel>
                  <FormControl>
                    <Input autoComplete="family-name" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <FormField
            control={form.control}
            name="tax_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.form.taxCode')}</FormLabel>
                <FormControl>
                  <Input autoComplete="off" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="vat_number"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.form.vatNumber')}</FormLabel>
                <FormControl>
                  <Input autoComplete="off" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        {isCompany && (
          <FormField
            control={form.control}
            name="sdi_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.form.sdiCode')}</FormLabel>
                <FormControl>
                  <Input autoComplete="off" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        {!isCompany && (
          <FormField
            control={form.control}
            name="birth_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.form.birthDate')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}
      </div>
    </Form>
  )
}

/** True when two drafts carry identical card fields (children ignored). */
function sameCardFields(a: PersonalDataDraft, b: PersonalDataDraft): boolean {
  return (
    a.type === b.type &&
    a.title === b.title &&
    a.first_name === b.first_name &&
    a.last_name === b.last_name &&
    a.company_name === b.company_name &&
    a.tax_code === b.tax_code &&
    a.vat_number === b.vat_number &&
    a.sdi_code === b.sdi_code &&
    a.birth_date === b.birth_date
  )
}
