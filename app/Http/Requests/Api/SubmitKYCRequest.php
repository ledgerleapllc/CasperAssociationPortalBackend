<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKYCRequest extends FormRequest
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
            'first_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'last_name' => 'required|regex:/^[A-Za-z. ]{1,255}$/',
            'dob' => 'required|date_format:m/d/Y|before:today',
            'address' => 'required',
            'city' => 'required',
            'zip' => 'required',
            'country_citizenship' => 'required',
            'country_residence' => 'required',
            'type' => 'required',
        ];
    }
}
