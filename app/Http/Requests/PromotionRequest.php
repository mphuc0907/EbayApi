<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
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
            'promotion_code' => 'unique:promotions|required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|min:0',
            'is_admin_created' => 'nullable|integer',
            'max_amount' => 'required|min:0',
            'kiosk_id' => 'required',
            'sub_kiosk_id' => 'nullable',
            'type' => 'required',
            'total_for_using' => 'required|min:0',
            'percent' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required',
            'created_user_id' => 'required',
        ];
    }
}
