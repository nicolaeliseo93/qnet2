<?php

namespace App\Http\Requests\CompanySites;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/company-sites/{companySite}/set-default (spec 0020): no body.
 * Authorization stays in the controller via CompanySitePolicy (`update`).
 */
class SetDefaultCompanySiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
