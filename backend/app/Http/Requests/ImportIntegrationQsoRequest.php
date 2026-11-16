<?php

namespace App\Http\Requests;

class ImportIntegrationQsoRequest extends StoreQsoRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['callsign_id' => $this->attributes->get('integration')->callsign_id]);
    }

    public function authorize(): bool
    {
        return parent::authorize() && in_array('qsos:write', $this->attributes->get('integration')->abilities, true);
    }

    public function rules(): array
    {
        return [...parent::rules(), 'external_id' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/D'], 'station_callsign' => ['sometimes', 'string', 'max:32']];
    }
}
