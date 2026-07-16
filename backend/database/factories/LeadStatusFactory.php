<?php

namespace Database\Factories;

use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    protected $model = LeadStatus::class;

    /**
     * The 14 badge color tokens shared by the color-token picker
     * (frontend/src/features/custom-fields/badge-color-tokens.ts) — `color`
     * is a palette TOKEN, never a hex value (D-2).
     *
     * @var array<int, string>
     */
    private const array COLOR_TOKENS = [
        'slate', 'gray', 'red', 'orange', 'amber', 'yellow', 'green',
        'emerald', 'teal', 'blue', 'indigo', 'violet', 'purple', 'pink',
    ];

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(self::COLOR_TOKENS),
            'sort_order' => self::$nextSortOrder++,
        ];
    }

    /**
     * Marks the row as a system status ('new' or 'closed'). Takes the
     * literal system_key string rather than the App\Enums\StatusSystemKey
     * case (backend ownership, not yet landed) so this factory has no
     * dependency on that class.
     */
    public function system(string $key): static
    {
        return $this->state(fn () => [
            'system_key' => $key,
            'name' => $key === 'new' ? 'Nuovo' : 'Chiuso',
            'color' => $key === 'new' ? 'slate' : 'green',
            'sort_order' => $key === 'new' ? 0 : 999,
        ]);
    }

    public function withGroup(int $statusGroupId): static
    {
        return $this->state(fn () => ['status_group_id' => $statusGroupId]);
    }
}
