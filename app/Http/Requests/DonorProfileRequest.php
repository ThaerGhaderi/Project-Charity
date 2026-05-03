<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DonorProfileRequest extends FormRequest
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
            'total_donated' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'skills.required' => 'Skills field is required.',
            'skills.max' => 'Skills cannot exceed 500 characters.',
            'availability.required' => 'Availability field is required.',
            'region.required' => 'Region field is required.',
            'total_donated.numeric' => 'Total donated must be a number.',
            'total_donated.min' => 'Total donated cannot be negative.'
        ];
    }
}