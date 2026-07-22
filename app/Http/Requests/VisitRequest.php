<?php
// app/Http/Requests/Visit/VisitRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRequest extends FormRequest
{
    
    protected function prepareForValidation(): void
    {
        
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
            'social_worker_id' => 'nullable|exists:users,id',
            'visit_type' => ['required', 'string', Rule::in([
                'زيارة باحث اجتماعي',
                'تحديث بيانات الأسرة',
                'زيارة طبية',
                'متابعة',
                'زيارة طارئة',
                'تقييم الحالة'
            ])],
            'location' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'visit_date' => 'required|date|after_or_equal:today',
            'visit_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'instructions' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.required' => 'يجب اختيار مستفيد',
            'beneficiary_id.exists' => 'المستفيد غير موجود',
            'visit_type.required' => 'نوع الزيارة مطلوب',
            'visit_type.in' => 'نوع الزيارة غير صالح',
            'location.required' => 'الموقع مطلوب',
            'visit_date.required' => 'تاريخ الزيارة مطلوب',
            'visit_date.after_or_equal' => 'تاريخ الزيارة يجب أن يكون اليوم أو تاريخ مستقبلي',
            'visit_time.required' => 'وقت الزيارة مطلوب',
            'visit_time.date_format' => 'صيغة الوقت غير صالحة',
        ];
    }
}