<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('organization')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $this->route('organization')->id)
                    ->ignore($this->route('department')->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
