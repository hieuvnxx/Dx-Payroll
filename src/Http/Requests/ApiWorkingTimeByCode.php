<?php

namespace Dx\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiWorkingTimeByCode extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => 'required',
            'month' => 'required',
        ];
    }

    /**
 * Get the error messages for the defined validation rules.
 *
 * @return array
 */
public function messages()
{
    return [
        'code.required' => 'The code field is required.',
        'month.required' => 'The month field is required.',
    ];
}
}
