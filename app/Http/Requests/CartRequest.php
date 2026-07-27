<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.campaign_id' => 'required|exists:campaigns,id',
            'items.*.amount' => 'required|numeric|min:1|max:100000',
        ];
    }
}
