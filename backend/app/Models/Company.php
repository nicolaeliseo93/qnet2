<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAddresses;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Company entity (spec 0010 — "Società aziendali"): a denomination, an
 * optional VAT number, and a single address (via HasAddresses, used here as
 * a single owned row rather than a real multi-address list — the invariant
 * is enforced by AddressService/CompanyService, not the schema).
 */
class Company extends BaseModel
{
    /** @use HasFactory<CompanyFactory> */
    use HasAddresses, HasFactory, LogsModelActivity;

    protected $fillable = [
        'denomination',
        'vat_number',
    ];

    protected $casts = [
        'denomination' => 'string',
        'vat_number' => 'string',
    ];

    /**
     * The company's single address, read off the `addresses` morph (first
     * primary row, falling back to any owned row). Convenience accessor for
     * callers that already eager-loaded `addresses`; does not itself trigger
     * a query when the relation is loaded.
     */
    protected function primaryAddress(): Attribute
    {
        return Attribute::get(function (): ?Address {
            $addresses = $this->addresses;

            return $addresses->firstWhere('is_primary', true) ?? $addresses->first();
        });
    }
}
