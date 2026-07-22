<?php
// app/Http/Requests/UpdateAidApplicationStatusRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAidApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && ($user->role === 'admin' || $user->role === 'Admin');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(\App\Models\AidApplication::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'amount_approved' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'status.in' => 'الحالة غير صالحة',
            'admin_notes.max' => 'الملاحظات طويلة جداً (الحد الأقصى 1000 حرف)',
            'amount_approved.numeric' => 'يجب أن يكون المبلغ رقماً',
            'amount_approved.min' => 'المبلغ يجب أن يكون أكبر من 0',
        ];
    }
}