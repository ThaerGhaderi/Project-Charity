<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VolunteerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => 'required|exists:cities,id',
            'photo_id' => 'required|image|mimes:jpg,jpeg,png|max:5000',
            'phone' => 'required|string|max:10|unique:profiles,phone',
            'birth_date' => 'required|date',
            'gender' => 'required|in:ذكر,انثى',
            'Personal_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',

            'Educational_level'=> 'required|in:ثانوية عامة,بكالوريوس,ماستر,دكتوراة,معهد',
            'Favorite_period'      => 'required|in:صباحاً,ظهراً,مساءً',
            'Commitment_type'      => 'required|in:منتظم,مرة بمرة',
            'experience_years'     => 'required|integer|min:0|max:50',

            'status' => 'nullable|in:منشغل,متاح,غير متاح',
            'skill_ids'   => 'required|array|min:1',
            'skill_ids.*' => 'exists:skills,id',
            'day_ids'   => 'required|array|min:1',
            'day_ids.*' => 'exists:days,id',
            'category_ids'    => 'required|array|min:1',
            'category_ids.*'  => 'exists:categories,id',
            'domain_ids'   => 'required|array|min:1',
            'domain_ids.*' => 'exists:domains,id',
            'language_ids'   => 'required|array|min:1',
            'language_ids.*' => 'exists:languages,id',

            'certificates'          => 'nullable|array|max:5',
            'certificates.*'        => 'image|mimes:jpg,jpeg,png|max:2048',
            'certificates_names'    => 'nullable|array',
            'certificates_names.*'  => 'nullable|string|max:255',
            'car' => 'required|boolean',
            'previous_voluntering' => 'required|boolean',
            'previous_work_place'  => 'required_if:previous_voluntering,1|nullable|string|max:255',
            'facebook' => 'nullable|url|max:255',
            'linkedin' => 'nullable|url|max:255',
            'pic_certificate' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
            'bio'=>'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
