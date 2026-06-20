<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'donation_id' => 'required|exists:donations,id',
            'reason' => 'required|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $donation = \App\Models\Donation::find($this->donation_id);
            
            if (!$donation) {
                $validator->errors()->add('donation_id', 'التبرع غير موجود');
            } elseif ($donation->user_id !== $this->user()->id) {
                $validator->errors()->add('donation_id', 'لا يمكنك طلب استرداد تبرع ليس لك');
            } elseif (!$donation->isRefundable()) {
                $validator->errors()->add('donation_id', 'لا يمكن استرداد هذا التبرع (انتهت فترة 24 ساعة)');
            } elseif ($donation->status !== 'completed') {
                $validator->errors()->add('donation_id', 'لا يمكن استرداد تبرع غير مكتمل');
            }
        });
    }
}