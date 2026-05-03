<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:volunteer,Donor,Beneficiary'
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'The role field is required',
            'role.in' => 'The role must be one of the following: volunteer, Donor, Beneficiary'
        ];
    }
}