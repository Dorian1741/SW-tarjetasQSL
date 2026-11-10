<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CallsignLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['callsign' => ['required', 'string', 'max:32'], 'provider' => ['sometimes', 'in:auto,qrz,hamqth']];
    }
}
