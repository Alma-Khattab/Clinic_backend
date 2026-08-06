<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
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
            'comment'        => 'required|string|min:5|max:1000',
            'is_anonymous'   => 'required|boolean', // 1 لإخفاء الاسم، 0 لإظهاره
        ];
    }
}
