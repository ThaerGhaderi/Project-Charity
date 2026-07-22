<?php
// app/Http/Requests/Visit/UpdateVisitRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitRequest extends FormRequest
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
        $user = $this->user();
        
        if (!$user) {
            return false;
        }

       
        $visitId = $this->route('visit');
        
        if (!$visitId) {
            return true;
        }

       
        $visit = \App\Models\Visit::find($visitId);
        
        
        if (!$visit) {
            return true;
        }

        $isAdmin = in_array($user->role, ['admin', 'Admin']);
        $isBeneficiary = $user->id === $visit->beneficiary_id;
        $isSocialWorker = $user->id === $visit->social_worker_id;

        return $isAdmin || $isBeneficiary || $isSocialWorker;
    }

   
    public function rules(): array
    {
        return [
            'visit_type' => ['sometimes', 'string', Rule::in([
                'زيارة باحث اجتماعي',
                'تحديث بيانات الأسرة',
                'زيارة طبية',
                'متابعة',
                'زيارة طارئة',
                'تقييم الحالة'
            ])],
            'location' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'visit_date' => 'sometimes|date|after_or_equal:today',
            'visit_time' => 'sometimes|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'instructions' => 'nullable|string|max:1000',
            'status' => ['sometimes', 'string', Rule::in([
                'قيد الانتظار',
                'مؤكدة',
                'مكتملة',
                'ملغية',
                'معاد جدولتها'
            ])],
            'cancelled_reason' => 'required_if:status,ملغية|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'visit_date.after_or_equal' => 'تاريخ الزيارة يجب أن يكون اليوم أو تاريخ مستقبلي',
            'cancelled_reason.required_if' => 'يجب كتابة سبب الإلغاء',
            'status.in' => 'الحالة غير صالحة',
            'visit_type.in' => 'نوع الزيارة غير صالح',
        ];
    }
}