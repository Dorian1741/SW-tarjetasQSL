<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'callsign_id' => ['required', 'integer', Rule::exists('callsigns', 'id')->where('user_id', $this->user()->id)->whereNotNull('reserved_callsign')],
            'biography' => ['nullable', 'string', 'max:3000'], 'country_code' => ['nullable', 'regex:/^[A-Z]{2}$/D'],
            'is_public' => ['required', 'boolean'], 'show_qsl_history' => ['required', 'boolean'],
            'links' => ['present', 'array', 'max:5'], 'links.*' => ['array:label,url'], 'links.*.label' => ['required', 'string', 'max:80'], 'links.*.url' => ['required', 'url:https', 'max:500', function ($attribute, $value, $fail) {
                $parts = parse_url($value);
                if (isset($parts['user']) || isset($parts['pass'])) {
                    $fail('No se permiten credenciales en enlaces públicos.');
                }
            }],
        ];
    }
}
