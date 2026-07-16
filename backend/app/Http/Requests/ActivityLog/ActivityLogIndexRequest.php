<?php

namespace App\Http\Requests\ActivityLog;

use App\DataObjects\ActivityLog\ActivityLogCursor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Validates GET /api/activity-log/{resource}/{id} (spec 0034): `per_page`
 * (1..100, default 25) and an opaque, well-formed `cursor`.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, which resolves the resource → model via ActivityLogRegistry
 * before it can check `{resource}.viewActivity`/Policy `view`).
 */
class ActivityLogIndexRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * A malformed (but present) cursor is a 422, not a silent "first page".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cursor = $this->query('cursor');

            if ($cursor === null || $cursor === '') {
                return;
            }

            try {
                ActivityLogCursor::decode($cursor);
            } catch (InvalidArgumentException) {
                $validator->errors()->add('cursor', 'The cursor is malformed.');
            }
        });
    }

    public function perPage(): int
    {
        $perPage = $this->validated('per_page');

        return $perPage === null ? self::DEFAULT_PER_PAGE : (int) $perPage;
    }

    public function cursor(): ?ActivityLogCursor
    {
        $cursor = $this->validated('cursor');

        return $cursor === null ? null : ActivityLogCursor::decode($cursor);
    }
}
