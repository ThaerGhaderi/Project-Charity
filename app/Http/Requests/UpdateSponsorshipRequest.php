<?php
// app/Http/Requests/UpdateSponsorshipRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSponsorshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        if (!$user) {
            return false;
        }

        // ✅ الحصول على الكفالة من الـ route
        $sponsorship = $this->route('id');
        
        // ✅ إذا لم يتم العثور على الكفالة، نسمح للمرور (الـ Controller سيتعامل معها)
        if (!$sponsorship) {
            return true;
        }

        // ✅ إذا كانت الكفالة كائن، تحقق من الصلاحية
        if (is_object($sponsorship)) {
            $isAdmin = in_array($user->role, ['admin', 'Admin']);
            $isSponsor = $user->id === $sponsorship->sponsor_id;
            
            // ✅ الكافل أو الأدمن فقط يمكنهم التحديث
            return $isAdmin || $isSponsor;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // ✅ نوع الكفالة
            'type' => ['sometimes', 'string', Rule::in(['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة'])],
            
            // ✅ المبلغ
            'amount' => 'sometimes|numeric|min:1|max:999999.99',
            
            // ✅ تاريخ الانتهاء
            'end_date' => 'nullable|date|after:today',
            
            // ✅ إخفاء هوية الكافل
            'is_anonymous' => 'sometimes|boolean',
            
            // ✅ رسالة الكافل للمستفيد
            'message' => 'nullable|string|max:500',
            
            // ✅ تجديد تلقائي
            'auto_renew' => 'sometimes|boolean',
            
            // ✅ حالة الكفالة
            'status' => ['sometimes', 'string', Rule::in(['نشطة', 'ملغية', 'معلقة'])],
            
            // ✅ سبب الإلغاء (مطلوب إذا كانت الحالة ملغية)
            'cancelled_reason' => 'required_if:status,ملغية|nullable|string|max:500',
            
            // ✅ طريقة الدفع
            'payment_method' => 'nullable|string|max:50',
            
            // ✅ تكرار الدفع
            'payment_frequency' => ['nullable', 'string', Rule::in(['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة'])],
            
            // ✅ العملة
            'currency' => 'nullable|string|in:SYP,USD,EUR',
            
            // ✅ تاريخ البدء
            'start_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    /**
     * Get the validation messages.
     */
    public function messages(): array
    {
        return [
            // ✅ رسائل الأخطاء بالعربية
            'amount.min' => 'المبلغ يجب أن يكون أكبر من 0',
            'amount.max' => 'المبلغ كبير جداً',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            
            'end_date.date' => 'تاريخ الانتهاء يجب أن يكون تاريخاً صحيحاً',
            'end_date.after' => 'تاريخ الانتهاء يجب أن يكون بعد اليوم',
            
            'cancelled_reason.required_if' => 'يجب كتابة سبب الإلغاء',
            'cancelled_reason.max' => 'سبب الإلغاء طويل جداً (الحد الأقصى 500 حرف)',
            
            'status.in' => 'الحالة غير صالحة',
            'status.string' => 'الحالة يجب أن تكون نصاً',
            
            'type.in' => 'نوع الكفالة غير صالح',
            'type.string' => 'نوع الكفالة يجب أن يكون نصاً',
            
            'is_anonymous.boolean' => 'حقل إخفاء الهوية يجب أن يكون true أو false',
            'auto_renew.boolean' => 'حقل التجديد التلقائي يجب أن يكون true أو false',
            
            'message.max' => 'الرسالة طويلة جداً (الحد الأقصى 500 حرف)',
            
            'payment_method.string' => 'طريقة الدفع يجب أن تكون نصاً',
            'payment_method.max' => 'طريقة الدفع طويلة جداً (الحد الأقصى 50 حرف)',
            
            'payment_frequency.in' => 'تكرار الدفع غير صالح',
            
            'currency.in' => 'العملة غير صالحة (SYP, USD, EUR)',
            
            'start_date.date' => 'تاريخ البدء يجب أن يكون تاريخاً صحيحاً',
            'start_date.after_or_equal' => 'تاريخ البدء يجب أن يكون اليوم أو تاريخ مستقبلي',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // ✅ حل مشكلة قراءة JSON
        if (empty($this->all())) {
            $jsonData = $this->json()->all();
            if (is_array($jsonData) && !empty($jsonData)) {
                $this->merge($jsonData);
            }
        }

        // ✅ تحويل القيم المنطقية من string إلى boolean
        if ($this->has('is_anonymous')) {
            $this->merge([
                'is_anonymous' => filter_var($this->is_anonymous, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('auto_renew')) {
            $this->merge([
                'auto_renew' => filter_var($this->auto_renew, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // ✅ تنظيف النصوص من المسافات الزائدة
        if ($this->has('message')) {
            $this->merge([
                'message' => trim($this->message),
            ]);
        }

        if ($this->has('cancelled_reason')) {
            $this->merge([
                'cancelled_reason' => trim($this->cancelled_reason),
            ]);
        }

        // ✅ إذا كان هناك تاريخ انتهاء، تأكد من أنه بعد تاريخ البدء
        if ($this->has('end_date') && $this->has('start_date')) {
            $endDate = $this->end_date;
            $startDate = $this->start_date;
            
            if ($endDate && $startDate && $endDate <= $startDate) {
                // سيتم التعامل مع هذا في الـ validation
                $this->merge([
                    'end_date' => null,
                ]);
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'نوع الكفالة',
            'amount' => 'المبلغ',
            'end_date' => 'تاريخ الانتهاء',
            'is_anonymous' => 'إخفاء الهوية',
            'message' => 'الرسالة',
            'auto_renew' => 'التجديد التلقائي',
            'status' => 'الحالة',
            'cancelled_reason' => 'سبب الإلغاء',
            'payment_method' => 'طريقة الدفع',
            'payment_frequency' => 'تكرار الدفع',
            'currency' => 'العملة',
            'start_date' => 'تاريخ البدء',
        ];
    }

    /**
     * Get the validation rules that apply to the request after validation.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // ✅ التحقق الإضافي: إذا كان status = نشطة، تأكد من أن الكفالة قيد الانتظار
            if ($this->has('status') && $this->status === 'نشطة') {
                $sponsorship = $this->route('id');
                if (is_object($sponsorship) && $sponsorship->status !== 'قيد الانتظار') {
                    $validator->errors()->add(
                        'status',
                        'لا يمكن تغيير حالة الكفالة إلى نشطة إلا إذا كانت قيد الانتظار'
                    );
                }
            }

            // ✅ التحقق الإضافي: إذا كان status = ملغية، تأكد من وجود سبب
            if ($this->has('status') && $this->status === 'ملغية') {
                if (empty($this->cancelled_reason)) {
                    $validator->errors()->add(
                        'cancelled_reason',
                        'يجب كتابة سبب الإلغاء'
                    );
                }
            }

            // ✅ التحقق الإضافي: المبلغ لا يمكن أن يكون صفر أو سالب
            if ($this->has('amount') && $this->amount <= 0) {
                $validator->errors()->add(
                    'amount',
                    'المبلغ يجب أن يكون أكبر من 0'
                );
            }
        });
    }
}