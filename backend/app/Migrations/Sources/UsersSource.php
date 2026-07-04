<?php

namespace App\Migrations\Sources;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\EmploymentData;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\PersonalTitleEnum;
use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * `users` migration source (spec 0013 AC-008/009, extended for the full
 * profile): creates the account, its personal-data card, primary address and
 * contacts, its role remap, and its employment profile — all through the
 * SAME UserService::create() the Users module itself uses (single creation
 * path, ADR 0012/0013/0015). Every relational reference (roles, manager,
 * business function, company, operational site) is an EXTERNAL id remapped
 * via `old_id`; an unresolved reference is a non-fatal warning, never fatal.
 *
 * The external system already sends a bcrypt HASH (never a plaintext
 * password): `CreateUserData` gets a throwaway random password so
 * UserService::create() satisfies its own invariants, then the row's
 * transaction (importRow()) overwrites `users.password` with the external
 * hash directly via the query builder — bypassing the model's `hashed` cast,
 * which would otherwise re-hash (and so invalidate) an already-hashed value.
 */
class UsersSource extends AbstractMigrationSource
{
    private const string DEFAULT_LOCALE = 'it';

    /**
     * `$2a$`/`$2b$`/`$2y$`, 2-digit cost, 53-char hash+salt — the standard
     * bcrypt shape (Laravel's own `Hash::make()` output), asserted so a
     * malformed/plaintext value is rejected rather than stored as-is.
     */
    private const string BCRYPT_PATTERN = '/^\$2[aby]\$\d{2}\$.{53}$/';

    public function __construct(
        ExternalApiClient $client,
        private readonly UserService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'password', 'label' => 'Password (bcrypt hash)', 'type' => 'string'],
            ['id' => 'first_name', 'label' => 'First name', 'type' => 'string'],
            ['id' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'tax_code', 'label' => 'Tax code', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'birth_date', 'label' => 'Birth date', 'type' => 'date'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
            ['id' => 'personal_email', 'label' => 'Personal email', 'type' => 'string'],
            ['id' => 'business_phone', 'label' => 'Business phone', 'type' => 'string'],
            ['id' => 'personal_phone', 'label' => 'Personal phone', 'type' => 'string'],
            ['id' => 'is_manager', 'label' => 'Is manager', 'type' => 'boolean'],
            ['id' => 'job_description', 'label' => 'Job description', 'type' => 'string'],
            ['id' => 'reports_to_id', 'label' => 'Reports to (external id)', 'type' => 'number'],
            ['id' => 'business_function_id', 'label' => 'Business function (external id)', 'type' => 'number'],
            ['id' => 'relationship_type', 'label' => 'Relationship type', 'type' => 'string'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'operational_site_id', 'label' => 'Operational site (external id)', 'type' => 'number'],
            ['id' => 'qualification_type', 'label' => 'Qualification type', 'type' => 'string'],
            ['id' => 'hired_at', 'label' => 'Hired at', 'type' => 'date'],
            ['id' => 'terminated_at', 'label' => 'Terminated at', 'type' => 'date'],
            ['id' => 'standard_daily_minutes', 'label' => 'Standard daily minutes', 'type' => 'number'],
            ['id' => 'break_daily_minutes', 'label' => 'Break daily minutes', 'type' => 'number'],
        ];
    }

    public function endpoint(): string
    {
        return 'users';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapRow(array $record): array
    {
        $row = [];

        foreach ($this->columns() as $column) {
            $row[$column['id']] = $record[$column['id']] ?? null;
        }

        return $row;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(User::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $email = trim((string) ($record['email'] ?? ''));
        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));
        $externalHash = (string) ($record['password'] ?? '');

        if ($email === '' || $firstName === '' || $lastName === '') {
            throw new RuntimeException('email, first_name and last_name are required.');
        }

        if (! preg_match(self::BCRYPT_PATTERN, $externalHash)) {
            throw new RuntimeException('password must already be a valid bcrypt hash.');
        }

        $warnings = [];

        [$roleNames, $roleWarnings] = $this->resolveRoleNames((array) ($record['role_ids'] ?? []));
        [$addressInput, $addressWarnings] = $this->buildAddress($record);
        [$contactInputs, $contactWarnings] = $this->buildContacts($record);
        [$employment, $employmentWarnings] = $this->buildEmployment($record);
        array_push($warnings, ...$roleWarnings, ...$addressWarnings, ...$contactWarnings, ...$employmentWarnings);

        $user = $this->service->create(
            $context->actor,
            new CreateUserData(
                email: $email,
                locale: self::DEFAULT_LOCALE,
                password: Str::password(24),
                roles: $roleNames === [] ? null : $roleNames,
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::Individual,
                    title: PersonalTitleEnum::fromValue($record['title'] ?? null),
                    firstName: $firstName,
                    lastName: $lastName,
                    taxCode: $this->blankToNull($record['tax_code'] ?? null),
                    vatNumber: $this->blankToNull($record['vat_number'] ?? null),
                    birthDate: $this->blankToNull($record['birth_date'] ?? null),
                ),
                contacts: $contactInputs === [] ? null : $contactInputs,
                addresses: $addressInput === null ? null : [$addressInput],
            ),
            $employment,
        );

        // The external hash is already bcrypt: writing it through the model's
        // `hashed` cast would re-hash (and invalidate) it, so it is set
        // directly on the column, inside the caller's per-row transaction.
        DB::table('users')->where('id', $user->id)->update(['password' => $externalHash]);

        $user->old_id = $externalId;
        $user->save();

        return MigrationRowOutcome::created($warnings);
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
