<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
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
            'appointment_id' => 'required|exists:appointments,id',
            'patient_id'       => 'required|exists:patients,user_id',
            'complaint'        => 'required|string|min:5',
            'blood_pressure'   => 'nullable|string|max:20',
            'heart_rate'       => 'nullable|integer|min:30|max:200',
            'temperature'      => 'nullable|numeric|between:30,45',
            'diagnosis'        => 'required|string|min:3',
            'prescription'     => 'nullable|string',
            'requested_tests'  => 'nullable|string',
        ];
    }
}
