<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientProfileRequest extends FormRequest
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
            'address'=>'sometimes|string|max:255',
            'height'=>'sometimes|integer',
            'weight'=>'sometimes|numeric',
            'job'=>'sometimes|string',
            'marital_status'=>'sometimes|string',
            'personal_image' =>"sometimes|image|mimes:png,jpg,jpeg,gif|max:10240",
            'blood_type'          => 'sometimes|string|max:10',
            'drug_allergies'      => 'sometimes|nullable|string',
            'chronic_diseases'    => 'sometimes|nullable|string',
            'previous_operations' => 'sometimes|nullable|string',
            'current_medicines'   => 'sometimes|nullable|string',
            'smoker'              => 'sometimes|boolean',
        ];
    }
}
