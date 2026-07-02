<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->word().'.pdf';

        return [
            'collection' => null,
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => $this->faker->numberBetween(1024, 1048576),
            'uploaded_by' => User::factory(),
        ];
    }
}
