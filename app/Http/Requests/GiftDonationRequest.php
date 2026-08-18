<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GiftDonationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'campaign_id'     => 'required|exists:campaigns,id',
            'amount'          => 'required|numeric|min:1',
            'currency'        => 'nullable|string|max:3',
            'payment_method'  => 'required|string',
            'recipient_name'  => 'required|string|max:255',
            'recipient_email' => 'required|email|max:255',
            'message'         => 'nullable|string|max:1000',
            'is_anonymous'    => 'nullable|boolean',
        ];
    }
}
