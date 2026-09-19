<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // A profile is always the authenticated user's own — the uniqueness check
        // ignores their existing row so re-submitting the same username doesn't fail.
        $existingProfileId = $this->user()?->profile?->id;

        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('user_profiles', 'username')->ignore($existingProfileId)],
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', Rule::exists('languages', 'code')->where('is_active', true)],
        ];
    }
}
