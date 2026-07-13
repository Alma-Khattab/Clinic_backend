<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
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
            'personal_image' =>"sometimes|image|mimes:png,jpg,jpeg,gif|max:2048",
            'address'=>'required|string|max:255',
            'blood_type'=>'required|string',
            'drug_allergies' => 'sometimes|nullable|string',
            'chronic_diseases' => 'sometimes|nullable|string',
            'previous_operations' => 'sometimes|nullable|string',
            'current_medicines' => 'sometimes|nullable|string',
            'height'=>'required|integer',
            'weight'=>'required|numeric',
            'job'=>'required|string',
            'smoker' => 'sometimes|boolean',
            'marital_status'=>'required|string'
        ];
    }
}
