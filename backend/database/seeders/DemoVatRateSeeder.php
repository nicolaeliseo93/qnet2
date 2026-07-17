<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

/**
 * Seed the initial VAT rate catalogue. Idempotent: each rate is created only
 * if a row with that name does not already exist, so re-running never
 * duplicates rows nor overwrites manual edits made through the `vat-rates`
 * CRUD module, mirroring DemoSourceSeeder.
 */
class DemoVatRateSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: float}>
     */
    private const array VAT_RATES = [
        ['IVA 22%', 22.00],
        ['IVA 10%', 10.00],
        ['IVA 5%', 5.00],
        ['IVA 4%', 4.00],
        ['Esente 0%', 0.00],
    ];

    public function run(): void
    {
        foreach (self::VAT_RATES as [$name, $rate]) {
            VatRate::firstOrCreate(['name' => $name], ['rate' => $rate]);
        }
    }
}
