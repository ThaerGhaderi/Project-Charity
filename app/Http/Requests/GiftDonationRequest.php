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
            'donation_id' => 'required|exists:donations,id',
            'recipient_name' => 'required|string|max:255',
            'recipient_email' => 'required|email|max:255',
            'message' => 'nullable|string|max:1000',
        ];
    }
}