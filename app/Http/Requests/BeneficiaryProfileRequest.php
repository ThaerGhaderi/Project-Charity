<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BeneficiaryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => 'required|string|max:500',
            'region' => 'required|string|max:255',
            'category' => 'required|in:orphan,refugee,disabled,poor',
            'birth_date' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'priority_score' => 'required|integer|min:0|max:100',
            'is_anonymized' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Address field is required.',
            'region.required' => 'Region field is required.',
            'category.required' => 'Category field is required.',
            'category.in' => 'Category must be: orphan, refugee, disabled, or poor.',
            'birth_date.required' => 'Birth date is required.',
            'birth_date.before' => 'Birth date must be before today.',
            'gender.required' => 'Gender is required.',
            'marital_status.required' => 'Marital status is required.',
            'priority_score.required' => 'Priority score is required.',
            'priority_score.min' => 'Priority score must be at least 0.',
            'priority_score.max' => 'Priority score cannot exceed 100.',
            'is_anonymized.required' => 'Please specify if you want to anonymize your profile.'
        ];
    }
}