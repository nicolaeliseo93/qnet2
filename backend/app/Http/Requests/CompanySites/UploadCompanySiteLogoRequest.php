<?php

namespace App\Http\Requests\CompanySites;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validates a company site logo upload (POST /api/company-sites/{companySite}/logo,
 * spec 0020). Authorization stays in the controller (`update`, gated same as
 * editing the site). The file is restricted to images server-side — the
 * frontend is never trusted — bounded by the shared attachments size limit;
 * `extensions:` additionally checks the real file extension against the
 * declared mimes (backend.md §8 — beats a spoofed MIME).
 */
class UploadCompanySiteLogoRequest extends FormRequest
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
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'extensions:jpeg,png,gif,webp', 'max:'.(int) config('attachments.max_size')],
        ];
    }

    public function logoFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('logo');

        return $file;
    }
}
