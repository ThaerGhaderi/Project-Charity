<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecurringDonationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:5|max:10000',
            'frequency' => 'required|in:daily,weekly,monthly',
            'payment_method' => 'required|in:stripe,paypal,tap',
        ];
    }
}