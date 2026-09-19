<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('member')) ?? false;
    }

    public function rules(): array
    {
        return [
            // Organization Owner is intentionally excluded — ownership transfer is
            // a deliberately deferred feature (see Phase 6 report), so this endpoint
            // can never be used to reassign or self-assign ownership.
            'role' => ['required', Rule::in(['Organization Admin', 'Member'])],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('organization_id', $this->route('organization')->id),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ];
    }
}
