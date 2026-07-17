<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\VatRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * VatRate lookup entity: a full-CRUD classification (name, rate) used to
 * assign a VAT percentage to a Product, mirroring Source (spec 0018).
 */
#[Fillable(['name', 'rate'])]
class VatRate extends BaseModel
{
    /** @use HasFactory<VatRateFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }
}
