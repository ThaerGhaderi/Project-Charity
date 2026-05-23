<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BeneficiaryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'Personal_photo'       => 'required|image|mimes:jpg,jpeg,png|max:5000',
            'phone'                => 'required|string|unique:profiles,phone',
            'gender'                => 'required|in:ذكر,انثى',
            'city_id'              => 'required|exists:cities,id',
            'birth_date' => 'required|date|before:today',

            'family_members_count'  => 'required|integer|min:1|max:15',
            'marital_status'        => 'required|in:أعزب,متزوج,مطلق,أرمل,يتيم',
            'Breadwinner'          => 'required|boolean',
            'has_income'           => 'required|boolean',
            'income_range' => 'required_if:has_income,1|nullable|in:أقل من 100 الف,100-300 الف,300-500 الف,أكثر من 500 الف',
            'is_Anonymous'         => 'required|boolean',
            'types' => 'required|array',
            'types.*' => 'exists:types,id',
            'photo_id' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
            'photo_Family_notebook'=> 'nullable|image|mimes:jpg,jpeg,png|max:5000',
            'photo_Supporting'     => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
        ];
    }
    public function messages(): array
    {
        return [

        ];
    }
}
