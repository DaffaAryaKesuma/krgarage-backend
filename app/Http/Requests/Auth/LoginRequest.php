<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi data yang dikirim saat user login.
class LoginRequest extends FormRequest {
    // rules() berisi syarat input sebelum controller AuthController@login dijalankan.
    public function rules(): array {
        return [
            // Email wajib ada, berupa teks, dan formatnya harus email.
            'email'    => 'required|string|email',
            // Password wajib ada dan berupa teks.
            'password' => 'required|string',
        ];
    }
}
