<?php

namespace App\Http\Requests\PersonalData;

use App\DataObjects\PersonalData\CreateAddress;
use App\Enums\SiteTypeEnum;
use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/addresses.
 *
 * Street line1 is the only required field; the geo references (city/state/
 * country) must point at real reference rows when supplied, and the coordinates
 * are bounded to their valid ranges. The address is attached to a polymorphic
 * owner (addressable_type/addressable_id) resolved through the config
 * allowlist. Authorization stays in the controller via the AddressPolicy.
 */
class StoreAddressRequest extends FormRequest
{
    use ResolvesOwner;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the AddressPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->domainRules(), $this->ownerRules());
    }

    /**
     * The address's own validation rules, owner-agnostic. Reused verbatim by
     * UpdateAddressRequest.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function domainRules(): array
    {
        return [
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'site_type' => ['nullable', Rule::enum(SiteTypeEnum::class)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Enforce a valid, existing owner.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOwner($validator));
    }

    protected function ownerConfigKey(): string
    {
        return 'personal_data.addressable_types';
    }

    protected function ownerTypeField(): string
    {
        return 'addressable_type';
    }

    protected function ownerIdField(): string
    {
        return 'addressable_id';
    }

    /**
     * The validated payload as a typed DTO.
     */
    public function toData(): CreateAddress
    {
        return new CreateAddress(
            line1: $this->string('line1')->toString(),
            line2: $this->input('line2'),
            postalCode: $this->input('postal_code'),
            siteType: $this->filled('site_type') ? SiteTypeEnum::from((string) $this->input('site_type')) : null,
            cityId: $this->filled('city_id') ? (int) $this->input('city_id') : null,
            provinceId: $this->filled('province_id') ? (int) $this->input('province_id') : null,
            stateId: $this->filled('state_id') ? (int) $this->input('state_id') : null,
            countryId: $this->filled('country_id') ? (int) $this->input('country_id') : null,
            latitude: $this->filled('latitude') ? (string) $this->input('latitude') : null,
            longitude: $this->filled('longitude') ? (string) $this->input('longitude') : null,
            isPrimary: $this->boolean('is_primary'),
        );
    }
}
