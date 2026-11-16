<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveStationCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($card = $this->route('stationCard')) {
            Gate::authorize('update', $card);
        }

        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('design'))) {
            $decoded = json_decode($this->input('design'), true);
            if (is_array($decoded)) {
                $this->merge(['design' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'callsign_id' => ['required', 'integer', Rule::exists('callsigns', 'id')->where('user_id', $this->user()->id)->whereNotNull('reserved_callsign')],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source' => ['required', 'in:upload,editor'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100,max_width=4096,max_height=4096'],
            'is_public' => ['required', 'boolean'],
            'design' => ['required_if:source,editor', 'nullable', 'array:template,background,accent,text,subtitle,location,locator,message,positions'],
            'design.template' => ['required_with:design', 'in:classic,aurora,sunset'],
            'design.background' => ['required_with:design', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'design.accent' => ['required_with:design', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'design.text' => ['required_with:design', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'design.subtitle' => ['nullable', 'string', 'max:80'],
            'design.location' => ['nullable', 'string', 'max:100'],
            'design.locator' => ['nullable', 'string', 'max:20'],
            'design.message' => ['nullable', 'string', 'max:120'],
            'design.positions' => ['required_with:design', 'array:callsign,subtitle,location,message'],
            'design.positions.callsign' => ['required_with:design', 'array:x,y'],
            'design.positions.subtitle' => ['required_with:design', 'array:x,y'],
            'design.positions.location' => ['required_with:design', 'array:x,y'],
            'design.positions.message' => ['required_with:design', 'array:x,y'],
            'design.positions.*' => ['array:x,y'],
            'design.positions.*.x' => ['required', 'numeric', 'between:30,1100'],
            'design.positions.*.y' => ['required', 'numeric', 'between:60,700'],
        ];
    }

    public function messages(): array
    {
        return ['image.max' => 'La imagen debe pesar como máximo 2 MB.', 'image.mimes' => 'Usa una imagen PNG, JPEG o WebP.', 'callsign_id.exists' => 'Selecciona uno de tus indicativos reservados.'];
    }
}
