<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validates an avatar upload (self-service or admin).
 *
 * Authorization is handled by the controller (the authenticated user for the
 * self endpoint, the UserPolicy for the admin endpoint). The file is restricted
 * to images server-side — the frontend is never trusted — and bounded by the
 * shared attachments size limit.
 */
class UploadAvatarRequest extends FormRequest
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
            'avatar' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:'.(int) config('attachments.max_size')],
        ];
    }

    public function avatarFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('avatar');

        return $file;
    }
}
