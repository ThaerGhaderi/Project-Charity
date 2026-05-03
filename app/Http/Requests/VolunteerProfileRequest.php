<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VolunteerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skills' => 'required|string|max:500',
            'availability' => 'required|string|max:255',
            'region' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'skills.required' => 'Skills field is required.',
            'skills.max' => 'Skills cannot exceed 500 characters.',
            'availability.required' => 'Availability field is required.',
            'region.required' => 'Region field is required.'
        ];
    }
}