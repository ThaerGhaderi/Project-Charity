<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DonorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
        'city_id'        => 'required|exists:cities,id',
        'photo_id'       => 'required|image|mimes:jpg,jpeg,png|max:5000',
        'phone'          => 'required|string|max:10|unique:profiles,phone',
        'birth_date'     => 'required|date',
        'gender'         => 'required|in:ذكر,انثى',
        'Personal_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
        // Donor specific
        'bio'            => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [

        ];
    }
}
