<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\EmploymentData;
use App\Enums\ContactTypeEnum;
use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Field-mapping helpers for UsersSource (spec 0013): translate one raw external
 * user record into the qnet value objects consumed by UserService::create()
 * (roles, primary address, contacts, employment, is_active). Extracted from the
 * source itself to keep it under the file-size budget; every relational
 * reference is remapped via `old_id` on the using AbstractMigrationSource.
 *
 * @phpstan-require-extends AbstractMigrationSource
 *
 * @property-read MigrationGeoResolver $geoResolver
 */
trait MapsExternalUserRecord
{
    /**
     * The external role references, as external ids, taken from the record's
     * `roles` array of `{id, name}` objects (the external contract's shape).
     * A flat `roles: [id, ...]` list is also tolerated. Each id is remapped to
     * a qnet role via `old_id` in resolveRoleNames().
     *
     * @param  array<string, mixed>  $record
     * @return array<int, int|string>
     */
    private function externalRoleIds(array $record): array
    {
        $roles = $record['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $ids = [];

        foreach ($roles as $role) {
            $externalId = is_array($role) ? ($role['id'] ?? null) : $role;

            if ($externalId !== null && $externalId !== '') {
                $ids[] = $externalId;
            }
        }

        return $ids;
    }

    /**
     * Remap the external role references to qnet role names via `old_id`. A
     * reference that resolves to no migrated role becomes a non-fatal
     * warning (the user is still created, just without that role).
     *
     * @param  array<int, int|string>  $externalRoleIds
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function resolveRoleNames(array $externalRoleIds): array
    {
        $names = [];
        $warnings = [];

        foreach ($externalRoleIds as $externalRoleId) {
            $roleId = $this->resolveOldId(Role::class, $externalRoleId);

            if ($roleId === null) {
                $warnings[] = "Unresolved role reference (external id {$externalRoleId}).";

                continue;
            }

            /** @var Role|null $role */
            $role = Role::query()->find($roleId);

            if ($role !== null) {
                $names[] = $role->name;
            }
        }

        return [$names, $warnings];
    }

    /**
     * Build the user's primary address from the external geo NAMES (same
     * approach as CompaniesSource/OperationalSitesSource), only when at least
     * a street or a city was supplied; a street-less city falls back to
     * being the address line itself. An unresolved geo level is a non-fatal
     * warning — the address is still created with whatever resolved.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: ?AddressInput, 1: array<int, string>}
     */
    private function buildAddress(array $record): array
    {
        $line1 = trim((string) ($record['street'] ?? ''));
        $city = trim((string) ($record['city'] ?? ''));

        if ($line1 === '' && $city === '') {
            return [null, []];
        }

        $geo = $this->geoResolver->resolve(
            $record['country'] ?? null,
            $record['region'] ?? null,
            $record['province'] ?? null,
            $record['city'] ?? null,
        );

        $address = new AddressInput(
            id: null,
            data: new CreateAddress(
                line1: $line1 !== '' ? $line1 : $city,
                postalCode: $this->blankToNull($record['postal_code'] ?? null),
                cityId: $geo->cityId,
                provinceId: $geo->provinceId,
                stateId: $geo->stateId,
                countryId: $geo->countryId,
                isPrimary: true,
            ),
        );

        return [$address, $geo->warnings];
    }

    /**
     * Build the user's contact channels: a personal email (primary) plus a
     * business/personal phone, each keyed by a free-text label (there is no
     * business/personal dimension on ContactTypeEnum itself). A present but
     * invalid value (fails the type's own `valueRules()`) is skipped with a
     * non-fatal warning rather than failing the whole row.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        $candidates = [
            ['field' => 'personal_email', 'type' => ContactTypeEnum::Email, 'label' => 'Personale', 'primary' => true],
            ['field' => 'business_phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Aziendale', 'primary' => false],
            ['field' => 'personal_phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Personale', 'primary' => false],
        ];

        $inputs = [];
        $warnings = [];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($record[$candidate['field']] ?? ''));

            if ($value === '') {
                continue;
            }

            if (! $this->isValidContactValue($candidate['type'], $value)) {
                $warnings[] = "Invalid {$candidate['field']} value, skipped.";

                continue;
            }

            $inputs[] = new ContactInput(id: null, data: new CreateContact(
                type: $candidate['type'],
                value: $value,
                label: $candidate['label'],
                isPrimary: $candidate['primary'],
            ));
        }

        return [$inputs, $warnings];
    }

    private function isValidContactValue(ContactTypeEnum $type, string $value): bool
    {
        return Validator::make(['value' => $value], ['value' => $type->valueRules()])->passes();
    }

    /**
     * Build the user's employment profile only when at least one employment
     * field was supplied (an absent employment section means "no employment
     * row", mirroring the wire contract's tri-state semantics). Every
     * relational reference (manager/business function/company/operational
     * site) resolves via `old_id`; an unresolved reference is a non-fatal
     * warning and leaves that link null. An unknown relationship/
     * qualification type is likewise a non-fatal warning.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: ?EmploymentData, 1: array<int, string>}
     */
    private function buildEmployment(array $record): array
    {
        if (! $this->hasEmploymentPayload($record)) {
            return [null, []];
        }

        $warnings = [];

        $reportsToId = $this->resolveEmploymentRelation(User::class, $record['reports_to_id'] ?? null, 'reports_to_id', $warnings);
        $businessFunctionId = $this->resolveEmploymentRelation(BusinessFunction::class, $record['business_function_id'] ?? null, 'business_function_id', $warnings);
        $companyId = $this->resolveEmploymentRelation(Company::class, $record['company_id'] ?? null, 'company_id', $warnings);
        $operationalSiteId = $this->resolveEmploymentRelation(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings);

        $employment = new EmploymentData(
            isManager: (bool) ($record['is_manager'] ?? false),
            jobDescription: $this->blankToNull($record['job_description'] ?? null),
            reportsToId: $reportsToId,
            businessFunctionId: $businessFunctionId,
            relationshipType: $this->resolveRelationshipType($record['relationship_type'] ?? null, $warnings),
            companyId: $companyId,
            operationalSiteId: $operationalSiteId,
            qualificationType: $this->resolveQualificationType($record['qualification_type'] ?? null, $warnings),
            hiredAt: $this->blankToNull($record['hired_at'] ?? null),
            terminatedAt: $this->blankToNull($record['terminated_at'] ?? null),
            standardDailyMinutes: $this->blankToInt($record['standard_daily_minutes'] ?? null),
            breakDailyMinutes: $this->blankToInt($record['break_daily_minutes'] ?? null),
        );

        return [$employment, $warnings];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function hasEmploymentPayload(array $record): bool
    {
        $employmentFields = [
            'is_manager', 'job_description', 'reports_to_id', 'business_function_id',
            'relationship_type', 'company_id', 'operational_site_id', 'qualification_type',
            'hired_at', 'terminated_at', 'standard_daily_minutes', 'break_daily_minutes',
        ];

        foreach ($employmentFields as $field) {
            if (array_key_exists($field, $record) && $record[$field] !== null && $record[$field] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<User|BusinessFunction|Company|OperationalSite>  $modelClass
     * @param  array<int, string>  $warnings
     */
    private function resolveEmploymentRelation(string $modelClass, mixed $externalRef, string $field, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId($modelClass, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved {$field} (external id {$externalRef}).";
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveRelationshipType(mixed $raw, array &$warnings): ?RelationshipTypeEnum
    {
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return null;
        }

        $case = RelationshipTypeEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved relationship_type '{$value}'.";
        }

        return $case;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveQualificationType(mixed $raw, array &$warnings): ?QualificationTypeEnum
    {
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return null;
        }

        $case = QualificationTypeEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved qualification_type '{$value}'.";
        }

        return $case;
    }

    /**
     * Absent/blank -> true (a migrated user is active unless the source
     * explicitly opts out), mirroring CreateUserData's own default. Accepts a
     * JSON boolean as well as the "1"/"0"/"true"/"false" string encodings.
     *
     * @param  array<string, mixed>  $record
     */
    private function resolveActive(array $record): bool
    {
        $value = $record['is_active'] ?? null;

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
