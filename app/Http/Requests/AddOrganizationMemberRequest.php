<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddOrganizationMemberRequest extends FormRequest
{
    // Organization context comes exclusively from the {organization} route
    // parameter, never from request input — see Phase 7 critical security
    // requirement. Only the target user is client-supplied, and only their ID;
    // role/status/organization are never accepted from the client here.
    public function authorize(): bool
    {
        return $this->user()?->can('create', [\App\Models\OrganizationMember::class, $this->route('organization')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
