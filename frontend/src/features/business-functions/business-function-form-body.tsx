import { Building2, Users as UsersIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useBusinessFunctionForm } from '@/features/business-functions/use-business-function-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { BUSINESS_FUNCTION_TYPES } from '@/features/business-functions/types'
import type {
  BusinessFunctionDetail,
  BusinessFunctionFormMode,
  BusinessFunctionType,
} from '@/features/business-functions/types'

interface BusinessFunctionFormBodyProps {
  mode: BusinessFunctionFormMode
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
  onCancel: () => void
}

/** Radix `Select` cannot hold an empty-string value: `type=null` ("no type") is represented by this sentinel and translated at the RHF boundary. */
const NONE_TYPE_VALUE = 'none'

/** i18n key per domain type, kept out of the JSX so the option list stays a plain map. */
const TYPE_LABEL_KEYS: Record<BusinessFunctionType, string> = {
  business_unit: 'businessFunctions.form.type.businessUnit',
  business_service: 'businessFunctions.form.type.businessService',
}

/**
 * The business-function create/edit form UI. Every field is wrapped in
 * `MetaField` (spec 0004): hidden fields are absent, non-editable fields
 * render disabled, `required` comes from the resolved `ResourcePermissions`
 * — no hardcoded permission logic lives here. All non-render logic lives in
 * `useBusinessFunctionForm`. Fields are grouped into two `FormSection` cards:
 * identity (name, type) and assignment (responsabile, associated users).
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero business-functions-specific rendering/validation logic.
 */
export function BusinessFunctionFormBody({ mode, onSuccess, onCancel }: BusinessFunctionFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, selectedManagerItem, selectedUserItems, onSubmit } =
    useBusinessFunctionForm({ mode, onSuccess })

  // Whole-section visibility, read from the same authorization context
  // `MetaField` uses: a card is only worth rendering if at least one of its
  // fields is visible. `MetaField` still gates each field individually — this
  // only decides whether the surrounding card is shown at all.
  const identityVisible = fieldPermission('name').visible || fieldPermission('type').visible
  const assignmentVisible =
    fieldPermission('manager_id').visible || fieldPermission('users').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={Building2}
              title={t('businessFunctions.form.sections.identity.title')}
              description={t('businessFunctions.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('businessFunctions.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="type"
                metaKey="type"
                label={t('businessFunctions.form.type.label')}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value ?? NONE_TYPE_VALUE}
                    onValueChange={(next) =>
                      field.onChange(next === NONE_TYPE_VALUE ? null : (next as BusinessFunctionType))
                    }
                    disabled={disabled}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value={NONE_TYPE_VALUE}>
                        {t('businessFunctions.form.type.none')}
                      </SelectItem>
                      {BUSINESS_FUNCTION_TYPES.map((type) => (
                        <SelectItem key={type} value={type}>
                          {t(TYPE_LABEL_KEYS[type])}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>
            </FormSection>
          )}

          {assignmentVisible && (
            <FormSection
              icon={UsersIcon}
              title={t('businessFunctions.form.sections.assignment.title')}
              description={t('businessFunctions.form.sections.assignment.description')}
            >
              <MetaField
                control={form.control}
                name="manager_id"
                metaKey="manager_id"
                label={t('businessFunctions.form.manager')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <AsyncPaginatedSelect
                      resource={USERS_FOR_SELECT_RESOURCE}
                      value={field.value}
                      onChange={field.onChange}
                      selectedItem={selectedManagerItem}
                      showAvatar
                      disabled={disabled}
                      labels={{
                        placeholder: t('businessFunctions.form.managerPlaceholder'),
                        searchPlaceholder: t('businessFunctions.form.usersSearch'),
                        empty: t('businessFunctions.form.usersEmpty'),
                        error: t('businessFunctions.form.usersError'),
                        clearLabel: t('common.clear'),
                        triggerLabel: t('businessFunctions.form.manager'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="users"
                metaKey="users"
                label={t('businessFunctions.form.users')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <AsyncPaginatedMultiSelect
                      resource={USERS_FOR_SELECT_RESOURCE}
                      value={field.value}
                      onChange={field.onChange}
                      selectedItems={selectedUserItems}
                      showAvatar
                      disabled={disabled}
                      labels={{
                        placeholder: t('businessFunctions.form.usersPlaceholder'),
                        searchPlaceholder: t('businessFunctions.form.usersSearch'),
                        empty: t('businessFunctions.form.usersEmpty'),
                        error: t('businessFunctions.form.usersError'),
                        removeLabel: t('businessFunctions.form.usersRemove'),
                        triggerLabel: t('businessFunctions.form.users'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          <CustomFieldsSection resource="business-functions" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('businessFunctions.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('businessFunctions.form.saving')
                : t('businessFunctions.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
