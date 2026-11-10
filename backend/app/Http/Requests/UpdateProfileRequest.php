<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'preferred_language' => ['sometimes', 'required', 'string', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/D'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'country_code' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/D'],
        ];
    }
}
