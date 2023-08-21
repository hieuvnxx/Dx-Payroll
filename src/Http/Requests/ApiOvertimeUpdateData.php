<?php

namespace Dx\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiOvertimeUpdateData extends FormRequest
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
            'Zoho_ID' => 'required',
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
        'Zoho_ID.required' => 'The Zoho_ID field is required.',
    ];
}
}
