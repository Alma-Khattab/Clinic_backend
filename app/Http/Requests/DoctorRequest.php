<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorRequest extends FormRequest
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
            'personal_image' =>"required|image|mimes:png,jpg,jpeg,gif|max:2048",
            'document_image'=>'required|image|mimes:png,jpg,jpeg,gif|max:2048',
            'doctor_specialization'=>'required|in:Cardiology,Ophthalmology,Dentistry,Pulmonology,Pediatrics,Gastroenterology,Neurology,General Surgery,Cosmetic Surgery',
            'bio'=>'required|max:255',
            'years_of_experience'=>'required|integer|max:255',
            'phone_number' => 'required|string|max:15'
        ];
    }
}
