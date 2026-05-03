<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required',
            'name.string' => 'The name must be a string',
            'name.max' => 'The name must not exceed 255 characters',
            'email.required' => 'The email field is required',
            'email.string' => 'The email must be a string',
            'email.email' => 'Please enter a valid email address',
            'email.max' => 'The email must not exceed 255 characters',
            'email.unique' => 'This email is already registered',
            'password.required' => 'The password field is required',
            'password.string' => 'The password must be a string',
            'password.min' => 'The password must be at least 8 characters',
            'password.confirmed' => 'The password confirmation does not match',
        ];
    }
}