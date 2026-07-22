<?php
// app/Http/Requests/SponsorshipRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SponsorshipRequest extends FormRequest
{
    /**
     * ✅ تجهيز البيانات قبل التحقق - حل مشكلة JSON
     */
    protected function prepareForValidation(): void
    {
        // ✅ إذا كانت البيانات فارغة، حاول قراءة من JSON
        if (empty($this->all())) {
            $jsonData = $this->json()->all();
            
            if (is_array($jsonData) && !empty($jsonData)) {
                $this->merge($jsonData);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'beneficiary_id' => 'required|exists:users,id',
            'type' => ['required', 'string', Rule::in(['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة'])],
            'amount' => 'required|numeric|min:1|max:999999.99',
            'currency' => 'nullable|string|in:SYP,USD,EUR',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'is_anonymous' => 'boolean',
            'message' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|in:card,bank_transfer,wallet',
            'payment_frequency' => ['nullable', 'string', Rule::in(['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة'])],
            'auto_renew' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.required' => 'يجب اختيار مستفيد',
            'beneficiary_id.exists' => 'المستفيد غير موجود',
            'type.required' => 'نوع الكفالة مطلوب',
            'type.in' => 'نوع الكفالة غير صالح',
            'amount.required' => 'مبلغ الكفالة مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من 0',
            'end_date.after' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البدء',
            'message.max' => 'الرسالة طويلة جداً (الحد الأقصى 500 حرف)',
        ];
    }
}