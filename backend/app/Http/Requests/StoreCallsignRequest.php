<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallsignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['callsign' => ['required', 'string', 'max:32'], 'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/D']];
    }
}
