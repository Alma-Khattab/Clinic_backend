<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
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
            'complaint'        => 'sometimes|required|string|min:5',
            'blood_pressure'   => 'nullable|string|max:20',
            'heart_rate'       => 'nullable|integer|min:30|max:200',
            'temperature'      => 'nullable|numeric|between:30,45',
            'diagnosis'        => 'sometimes|required|string|min:3',
            'prescription'     => 'nullable|string',
            'requested_tests'  => 'nullable|string',
        ];
    }
}
