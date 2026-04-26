<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
             'email' => 'required|string|email|max:255|exists:users,email',
            'password' => 'required|string|min:8'
        ];
    }
       public function messages(): array
    {
        return [
            'email.required' => 'The email field is required',
            'email.string' => 'The email must be a string',
            'email.email' => 'Please enter a valid email address',
            'email.max' => 'The email must not exceed 255 characters',
            'email.exists' => 'This email is not registered in our system',
            'password.required' => 'The password field is required',
            'password.string' => 'The password must be a string',
            'password.min' => 'The password must be at least 8 characters'
        ];
    }
}
