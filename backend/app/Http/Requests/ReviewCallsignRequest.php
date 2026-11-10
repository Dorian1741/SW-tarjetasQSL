<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCallsignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['verified', 'rejected', 'disputed'])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
