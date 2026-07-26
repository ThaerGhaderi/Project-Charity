<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'donor_id' => 'nullable|exists:donor_profiles,id',
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:1|max:100000',
            'currency' => 'sometimes|string|size:3',
            'payment_method' => 'required|in:stripe,paypal,tap,moyasar,mada,apple_pay,google_pay,crypto,payerurl',
            'is_anonymous' => 'boolean',
            'is_recurring' => 'boolean',
            'is_gift' => 'boolean',
            'on_behalf_of' => 'nullable|string|max:100',
            'gift_message' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campaign_id.required' => 'يرجى اختيار الحملة',
            'campaign_id.exists' => 'الحملة غير موجودة',
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون على الأقل $1',
            'amount.max' => 'المبلغ كبير جداً',
            'payment_method.required' => 'يرجى اختيار طريقة الدفع',
            'payment_method.in' => 'طريقة الدفع غير صالحة',
            'currency.size' => 'العملة يجب أن تكون 3 أحرف',
        ];
    }
}
