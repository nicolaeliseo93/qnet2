<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\CompanySiteBankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank account owned by a company site (spec 0020), a real 1→N child (FK
 * `company_site_id`, not a morph) — see BankService for the diff-by-id sync
 * invariant, mirroring ContactService for the polymorphic contacts.
 */
class CompanySiteBank extends BaseModel
{
    /** @use HasFactory<CompanySiteBankFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'iban',
        'notes',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        // Spec 0013 — external data migration: guarded (not in $fillable), only
        // ever set by property assignment post-create.
        'old_id' => 'integer',
    ];

    public function companySite(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class);
    }
}
