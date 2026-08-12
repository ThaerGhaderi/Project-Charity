<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EvaluateVolunteerTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true; // الصلاحية بتتفحص بالـ middleware/Controller
    }

    public function rules()
    {
        return [
            'rating' => ['required', 'numeric', 'between:1,10'],
            'feedback' => ['nullable','string', 'max:1000'],
        ];
    }
}
