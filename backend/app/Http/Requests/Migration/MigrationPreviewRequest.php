<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/migrations/{source}/preview (spec 0013): `page` (>=1,
 * default 1) and `per_page` (1..MIGRATIONS_MAX_PER_PAGE, default
 * MIGRATIONS_DEFAULT_PER_PAGE — config/migrations.php).
 *
 * Authorization is NOT handled here: the route group's `super-admin`
 * middleware alias (EnsureSuperAdmin) gates every migrations endpoint before
 * this request runs.
 */
class MigrationPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('migrations.max_per_page');

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    public function pageNumber(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function perPageSize(): int
    {
        return (int) ($this->validated('per_page') ?? config('migrations.default_per_page'));
    }
}
