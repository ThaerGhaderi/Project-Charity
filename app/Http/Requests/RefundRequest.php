<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Donation;
use App\Models\DonorProfile;
use App\Models\RefundRequest as RefundRequestModel; // ✅ إضافة هذا

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donation_id' => 'required|exists:donations,id',
            'reason' => 'required|string|max:500|min:10',
        ];
    }

    public function messages(): array
    {
        return [
            'donation_id.required' => 'يرجى تحديد التبرع المراد استرداده',
            'donation_id.exists' => 'التبرع المحدد غير موجود',
            'reason.required' => 'يرجى كتابة سبب طلب الاسترداد',
            'reason.min' => 'سبب الاسترداد يجب أن يكون على الأقل 10 أحرف',
            'reason.max' => 'سبب الاسترداد لا يمكن أن يتجاوز 500 حرف',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $donation = Donation::find($this->donation_id);

            if (!$donation) {
                $validator->errors()->add('donation_id', 'التبرع غير موجود');
                return;
            }

            $donor = DonorProfile::where('user_id', $this->user()->id)->first();

            if (!$donor) {
                $validator->errors()->add('donation_id', 'لم يتم العثور على ملف المتبرع الخاص بك');
                return;
            }

            if ((int) $donation->donor_id !== (int) $donor->id) {
                $validator->errors()->add('donation_id', 'لا يمكنك طلب استرداد تبرع ليس لك');
                return;
            }

            if ($donation->status !== 'completed') {
                $validator->errors()->add('donation_id', 'لا يمكن استرداد تبرع غير مكتمل');
                return;
            }

            if (!$donation->isRefundable()) {
                $validator->errors()->add('donation_id', 'لا يمكن استرداد هذا التبرع (انتهت فترة الاسترداد المسموحة)');
                return;
            }

            // ✅ تصحيح اسم الكلاس
            $existingRequest = RefundRequestModel::where('donation_id', $donation->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                $validator->errors()->add('donation_id', 'تم تقديم طلب استرداد لهذا التبرع بالفعل');
                return;
            }
        });
    }
}