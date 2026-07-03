<?php

namespace Database\Factories;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentProfile>
 */
class EmploymentProfileFactory extends Factory
{
    protected $model = EmploymentProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hiredAt = fake()->dateTimeBetween('-5 years', '-1 month');

        return [
            'user_id' => User::factory(),
            'is_manager' => false,
            'job_description' => fake()->optional()->jobTitle(),
            'reports_to_id' => null,
            'business_function_id' => null,
            'relationship_type' => fake()->randomElement(RelationshipTypeEnum::values()),
            'company_id' => null,
            'operational_site_id' => null,
            'qualification_type' => fake()->randomElement(QualificationTypeEnum::values()),
            'hired_at' => $hiredAt->format('Y-m-d'),
            'terminated_at' => null,
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
        ];
    }

    /**
     * A responsible manager: never reports to anyone (spec 0015 server rule).
     */
    public function manager(): static
    {
        return $this->state(fn (): array => [
            'is_manager' => true,
            'reports_to_id' => null,
        ]);
    }

    /**
     * A subordinate reporting to the given manager.
     */
    public function reportsTo(User $manager): static
    {
        return $this->state(fn (): array => [
            'is_manager' => false,
            'reports_to_id' => $manager->id,
        ]);
    }
}
