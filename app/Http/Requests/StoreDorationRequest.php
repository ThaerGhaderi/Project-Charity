<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDorationRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
    $isWebQuickDonation = ! $this->has('donor_email');
    if ($isWebQuickDonation) {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount'     => ['required', 'integer', 'min:1'],
            'payment_method'     => ['required', 'in:نقد,بطاقة مصرفية,محفظة موبايل,تحويل بنكي,حوالة'],
            'cat'        => ['required', 'in:إطعام,مساجد,تعليم,صحة,مياه,أيتام'],
            'date'       => ['required', 'date'],
            'notes'      => ['nullable', 'string'],
        ];
    }

    return [
        'donor_name'   => ['required', 'string', 'max:255'],
        'donor_email'  => ['required', 'email', 'max:255'],
        'donor_type'   => ['nullable', 'in:فردي,منظمة'],
        'is_anonymous' => ['nullable', 'boolean'],
        'amount'       => ['required', 'integer', 'min:1'],
        'method'       => ['required', 'in:نقد,بطاقة مصرفية,محفظة موبايل,تحويل بنكي,حوالة'],
        'cat'          => ['required', 'in:إطعام,مساجد,تعليم,صحة,مياه,أيتام'],
        'date'         => ['required', 'date'],
        'status'       => ['required', 'in:مكتمل,ملغي,قيد المراجعة'],
        'notes'        => ['nullable', 'string'],
    ];
    }
    public function messages(): array
    {
        return [
            'donor_name.required'  => 'اسم المتبرع مطلوب',
            'donor_email.required' => 'البريد الإلكتروني للمتبرع مطلوب',
            'donor_email.email'    => 'البريد الإلكتروني غير صالح',
            'amount.min'           => 'مبلغ التبرع يجب أن يكون أكبر من صفر',
            'method.in'            => 'طريقة الدفع غير مدعومة',
            'cat.in'               => 'التصنيف غير مدعوم',
            'status.in'            => 'الحالة غير مدعومة',
        ];
    }
}
