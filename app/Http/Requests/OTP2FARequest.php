<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OTP2FARequest extends FormRequest
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
            '2fa_secret' => 'required|min:16|max:16',
        ];
    }
}
