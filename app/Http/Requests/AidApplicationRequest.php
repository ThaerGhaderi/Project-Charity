<?php
// app/Http/Requests/AidApplicationRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AidApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', Rule::in(\App\Models\AidApplication::TYPES)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'is_urgent' => ['sometimes', 'boolean'],
            'amount_requested' => ['nullable', 'numeric', 'min:0'],
        ];

        // For update, make fields sometimes
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['type'] = ['sometimes', 'string', Rule::in(\App\Models\AidApplication::TYPES)];
            $rules['description'] = ['sometimes', 'string', 'min:10', 'max:2000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'نوع المساعدة مطلوب',
            'type.in' => 'نوع المساعدة غير صالح',
            'description.required' => 'وصف الطلب مطلوب',
            'description.min' => 'يجب أن يكون الوصف 10 أحرف على الأقل',
            'description.max' => 'الوصف طويل جداً (الحد الأقصى 2000 حرف)',
            'amount_requested.numeric' => 'يجب أن يكون المبلغ رقماً',
            'amount_requested.min' => 'المبلغ يجب أن يكون أكبر من 0',
        ];
    }
}