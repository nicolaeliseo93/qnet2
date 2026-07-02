<?php

namespace App\Models;

use App\Enums\PersonalDataTypeEnum;
use App\Enums\PersonalTitleEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAddresses;
use App\Models\Concerns\HasContacts;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PersonalDataFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic personal-data card — the identity sheet of an owning
 * entity. Owners attach exactly one card via the HasPersonalData trait
 * (morphOne on `personable`):
 *
 *     class User extends Authenticatable
 *     {
 *         use HasPersonalData;
 *     }
 *
 *     $user->personalData;            // the card (or null)
 *     $user->personalData->full_name; // computed
 *
 * A card itself owns its contacts (HasContacts) and addresses (HasAddresses),
 * which cascade away when the card is deleted.
 *
 * @property PersonalDataTypeEnum $type
 * @property PersonalTitleEnum|null $title
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $company_name
 * @property string|null $sdi_code
 */
class PersonalData extends BaseModel
{
    /** @use HasFactory<PersonalDataFactory> */
    use HasAddresses, HasContacts, HasFactory, LogsModelActivity;

    protected $table = 'personal_data';

    protected $fillable = [
        'type',
        'title',
        'first_name',
        'last_name',
        'company_name',
        'tax_code',
        'vat_number',
        'sdi_code',
        'birth_date',
    ];

    protected $casts = [
        'type' => PersonalDataTypeEnum::class,
        'title' => PersonalTitleEnum::class,
        'first_name' => 'string',
        'last_name' => 'string',
        'company_name' => 'string',
        'tax_code' => 'string',
        'vat_number' => 'string',
        'sdi_code' => 'string',
        'birth_date' => 'date',
    ];

    /**
     * Truly sensitive identifiers. Hiding them here keeps them out of the
     * activity log (LogsModelActivity excludes $hidden) AND out of default JSON
     * serialization, avoiding over-exposure. Names stay visible so the audit
     * trail and any future resource remain human-readable; they can still be
     * subjected to GDPR erasure on the row itself.
     *
     * @var list<string>
     */
    protected $hidden = [
        'tax_code',
        'vat_number',
        'birth_date',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function personable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * Human-readable name of the card: the company name for a company, the
     * trimmed "first last" for an individual. Empty string when nothing is set,
     * never null, so callers can display it directly.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->type === PersonalDataTypeEnum::Company) {
                return (string) ($this->company_name ?? '');
            }

            return trim(implode(' ', array_filter([$this->first_name, $this->last_name])));
        });
    }

    /**
     * The legal representative shown for a company card. Mirrors the
     * individual-style name of the person running the company; for an
     * individual card there is no CEO, so it resolves to null.
     */
    protected function ceo(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->type !== PersonalDataTypeEnum::Company) {
                return null;
            }

            $name = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

            return $name === '' ? null : $name;
        });
    }
}
