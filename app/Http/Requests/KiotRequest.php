<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KiotRequest extends FormRequest
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
            'name' => 'required|min:2|max:255',
            'category_parent_id' => 'required',
            'category_id' => 'required|max:26',
            'category_sub_id' => 'required|max:26',
            'refund_person' => 'required|max:11',
            'image' => 'required|min:1|max:255',
            'description' => 'required',
        ];
    }
}
