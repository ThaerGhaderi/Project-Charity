<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
             'name'=>'Required|string|max:255',
                'email'=>'Required|string|email|max:255|unique:users,email',
                  'password'=>'Required|string|min:8|confirmed',
                  'phone'=>'Required|string|unique:users,phone',
                 'role'=>'required|in:volunteer,Donor,Beneficiary'
       
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
            'phone.required' => 'The phone number field is required',
            'phone.string' => 'The phone number must be a string',
            'phone.max' => 'The phone number must not exceed 20 characters',
            'phone.unique' => 'This phone number is already registered',
            'role.required' => 'The role field is required',
            'role.in' => 'The role must be one of the following: volunteer, Donor, Beneficiary'
        ];
    }
}
