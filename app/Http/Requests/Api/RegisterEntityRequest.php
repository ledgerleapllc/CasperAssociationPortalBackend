<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterEntityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|string|max:255',
            'entity_register_number' => 'required|string|max:255',
            'entity_register_country' => 'required|string|max:255',
            'entity_tax' => 'nullable|string|max:255',
            'first_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'last_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'email' => 'required|email|max:256|unique:users',
            'password' => 'required|min:8|max:80',
            'pseudonym' => 'required|alpha_num|max:200|unique:users',
            'telegram' => 'nullable|regex:/^[@][a-zA-Z0-9_-]+$/',
            // 'validatorAddress' => 'required',
        ];
    }
}
