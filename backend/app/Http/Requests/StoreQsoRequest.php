<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'callsign_id' => ['required', 'integer', Rule::exists('callsigns', 'id')->where('user_id', $this->user()->id)],
            'contacted_callsign' => ['required', 'string', 'max:32'],
            'frequency_khz' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,3})?$/D', 'numeric', 'gt:0', 'max:999999999.999'],
            'band' => ['required', Rule::in(['2190m', '630m', '160m', '80m', '60m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m', '4m', '2m', '1.25m', '70cm', '33cm', '23cm', '13cm', '9cm', '6cm', '3cm'])],
            'mode' => ['required', Rule::in(['SSB', 'CW', 'FM', 'AM', 'FT8', 'FT4', 'RTTY', 'PSK31', 'JS8', 'DSTAR', 'DMR', 'C4FM'])],
            'started_at' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'before_or_equal:now'],
            'ended_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z', 'after_or_equal:started_at', 'before_or_equal:now'],
            'rst_sent' => ['nullable', 'string', 'max:10'], 'rst_received' => ['nullable', 'string', 'max:10'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
